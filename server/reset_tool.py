import logging
import sys
from pve_manager import PVEManager, init_db

logging.basicConfig(level=logging.INFO,
                    format='%(asctime)s - %(levelname)s - %(message)s',
                    stream=sys.stdout)

def run_system_reset():
    print("===================================================================")
    print("==                 ZJMF-PVE 系统重置工具                     ==")
    print("===================================================================")
    print("\n\033[91m警告：这是一个非常危险的操作，将执行以下所有内容：\033[0m")
    print("  1. \033[93m停止并永久删除\033[0m PVE 上的 \033[93m所有\033[0m LXC 容器。")
    print("  2. 清除与这些容器相关的 \033[93m所有\033[0m NAT (iptables) 规则。")
    print("  3. \033[91m彻底删除并重建\033[0m 本地数据库 (`pve_local_data.db`)。")
    print("\n\033[1m此操作不可逆，所有数据将会丢失！\033[0m\n")

    confirm1 = input("您是否完全理解以上后果并希望继续？请输入 'yes' 确认: ")
    if confirm1.lower() != 'yes':
        print("操作已取消。")
        return

    confirm2 = input("这是最后一次机会。请输入 'CONFIRM RESET' 以启动重置程序: ")
    if confirm2 != 'CONFIRM RESET':
        print("操作已取消。")
        return

    print("\n确认完毕，开始执行系统重置...\n")

    try:
        logging.info("正在初始化 PVE 管理器...")
        pve = PVEManager()
        logging.info("PVE 管理器初始化成功。")

        success, message = pve.reset_system()

        if success:
            logging.info(f"\n\033[92m{message}\033[0m")
        else:
            logging.error(f"\n\033[91m重置失败: {message}\033[0m")

    except RuntimeError as e:
        logging.critical(f"无法初始化PVEManager，请检查配置文件和PVE连接: {e}")
    except Exception as e:
        logging.critical(f"在重置过程中发生未知错误: {e}", exc_info=True)

if __name__ == '__main__':
    init_db() 
    run_system_reset()