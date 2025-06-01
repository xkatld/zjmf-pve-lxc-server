from sqlalchemy import Column, Integer, String, DateTime, Text, UniqueConstraint, ForeignKey, Boolean
from sqlalchemy.sql import func
from .database import Base

class OperationLog(Base):
    __tablename__ = "operation_logs"

    id = Column(Integer, primary_key=True, index=True)
    operation = Column(String(50), index=True)
    container_id = Column(String(20), index=True)
    node_name = Column(String(50))
    status = Column(String(20))
    message = Column(Text)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    ip_address = Column(String(45))
    task_id = Column(String(255), nullable=True, index=True)

class NatRule(Base):
    __tablename__ = "nat_rules"

    id = Column(Integer, primary_key=True, index=True)
    node = Column(String, nullable=False, index=True)
    vmid = Column(Integer, nullable=False, index=True)
    host_port = Column(Integer, nullable=False)
    container_port = Column(Integer, nullable=False)
    protocol = Column(String(3), nullable=False)
    container_ip_at_creation = Column(String, nullable=False)
    description = Column(String, nullable=True)
    enabled = Column(Boolean, default=True, nullable=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now(), server_default=func.now())

    __table_args__ = (UniqueConstraint('host_port', 'protocol', name='uq_host_port_protocol'),)
