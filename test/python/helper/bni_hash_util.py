import base64
import time


def hash_data(json_data: str, client_id: str, secret_key: str) -> str:
    time_str = str(int(time.time() * 1000))[:10]
    time_str = time_str[::-1]
    return _double_encrypt(f"{time_str}.{json_data}", client_id, secret_key)


def _encrypt(data: bytes, key: str) -> bytes:
    key_len = len(key)
    result = bytearray(len(data))
    for i, byte in enumerate(data):
        key_char = ord(key[(i + key_len - 1) % key_len])
        result[i] = (byte + key_char) % 128
    return bytes(result)


def _double_encrypt(input_str: str, client_id: str, secret_key: str) -> str:
    encrypted = _encrypt(input_str.encode(), client_id)
    encrypted = _encrypt(encrypted, secret_key)
    encoded = base64.b64encode(encrypted).decode()
    return encoded.rstrip("=").replace("+", "-").replace("/", "_")
