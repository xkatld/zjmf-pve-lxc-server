from fastapi import HTTPException, Depends, Request
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from sqlalchemy.orm import Session
from .database import get_db
from .models import OperationLog
from .config import settings

security = HTTPBearer()

def verify_api_key(credentials: HTTPAuthorizationCredentials = Depends(security)):
    if not credentials or credentials.credentials != settings.global_api_key:
        raise HTTPException(status_code=401, detail="无效的API密钥或未提供")

def log_operation(
    db: Session,
    operation: str,
    container_id: str,
    node_name: str,
    status: str,
    message: str,
    ip_address: str = None,
    task_id: str = None
):
    log_entry = OperationLog(
        operation=operation,
        container_id=container_id,
        node_name=node_name,
        status=status,
        message=message,
        ip_address=ip_address,
        task_id=task_id
    )

    db.add(log_entry)
    db.commit()
