from contextvars import ContextVar

request_task_id_cv: ContextVar[str] = ContextVar("request_task_id_cv", default="NO_TASK_ID_SET")
