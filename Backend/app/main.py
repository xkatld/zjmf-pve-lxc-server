from fastapi import FastAPI, HTTPException, Request as FastAPIRequest
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import logging
from logging.handlers import TimedRotatingFileHandler
import sys
import datetime
import uuid
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request as StarletteRequest


from .config import settings
from .database import create_tables
from .api import router as api_router
from .logging_context import request_task_id_cv


class ContextVarFilter(logging.Filter):
    def filter(self, record):
        record.request_task_id_cv = request_task_id_cv.get()
        return True

log_formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(request_task_id_cv)s - %(message)s')

log_file = 'lxc_api.log'
file_handler = TimedRotatingFileHandler(
    log_file,
    when="midnight",
    interval=1,
    backupCount=7,
    encoding='utf-8'
)
file_handler.setFormatter(log_formatter)
file_handler.addFilter(ContextVarFilter())

stream_handler = logging.StreamHandler(sys.stdout)
stream_handler.setFormatter(log_formatter)
stream_handler.addFilter(ContextVarFilter())

root_logger = logging.getLogger()
root_logger.setLevel(logging.INFO)
root_logger.addHandler(file_handler)
root_logger.addHandler(stream_handler)

logger = logging.getLogger(__name__)

app = FastAPI(
    title=settings.api_title,
    description=settings.api_description,
    version=settings.api_version,
    docs_url="/docs",
    redoc_url="/redoc"
)

class RequestContextLogMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: StarletteRequest, call_next: "RequestResponseCallNext"):
        token = request_task_id_cv.set(str(uuid.uuid4()))
        task_id_for_header_and_log = request_task_id_cv.get()

        logger.info(f"请求开始: {request.method} {request.url.path} 从 {request.client.host}")

        response = await call_next(request)

        response.headers["X-Task-ID"] = task_id_for_header_and_log
        logger.info(f"请求完成: {request.method} {request.url.path} - 状态码 {response.status_code}")

        request_task_id_cv.reset(token)
        return response

app.add_middleware(RequestContextLogMiddleware)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.exception_handler(Exception)
async def global_exception_handler(request: FastAPIRequest, exc: Exception):
    request_id = request_task_id_cv.get()
    logger.error(f"全局异常处理: {str(exc)}", exc_info=True)
    return JSONResponse(
        status_code=500,
        content={"error": "服务器内部错误", "detail": str(exc), "task_id": request_id}
    )

@app.on_event("startup")
async def startup_event():
    logger.info("正在启动 LXC 管理 API 服务...")
    create_tables()
    logger.info("数据库表创建完成（或已存在）")
    logger.info(f"API 服务启动成功，请通过 http://<您的IP>:8000/docs 访问")

@app.on_event("shutdown")
async def shutdown_event():
    logger.info("LXC 管理 API 服务正在关闭...")

@app.get("/", summary="服务状态检查", tags=["服务状态"])
async def root():
    return {
        "service": "Proxmox LXC 管理 API",
        "version": settings.api_version,
        "status": "运行中",
        "docs": "/docs",
        "task_id": request_task_id_cv.get()
    }

@app.get("/health", summary="健康检查", tags=["服务状态"])
async def health_check():
    return {
        "status": "健康",
        "service": "lxc-api",
        "timestamp": datetime.datetime.now().isoformat(),
        "task_id": request_task_id_cv.get()
    }

app.include_router(api_router, prefix="/api/v1")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
        log_level="info"
    )
