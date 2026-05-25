function encrypt(data: Buffer, key: string): Buffer {
  const keyLen = key.length;
  const result = Buffer.alloc(data.length);
  for (let i = 0; i < data.length; i++) {
    const keyChar = key.charCodeAt((i + keyLen - 1) % keyLen);
    result[i] = (data[i] + keyChar) % 128;
  }
  return result;
}

function doubleEncrypt(input: string, clientId: string, secretKey: string): string {
  let encrypted = encrypt(Buffer.from(input, 'utf8'), clientId);
  encrypted = encrypt(encrypted, secretKey);
  return encrypted
    .toString('base64')
    .replace(/=+$/, '')
    .replace(/\+/g, '-')
    .replace(/\//g, '_');
}

export function hashData(jsonData: string, clientId: string, secretKey: string): string {
  let time = String(Date.now()).slice(0, 10);
  time = time.split('').reverse().join('');
  return doubleEncrypt(`${time}.${jsonData}`, clientId, secretKey);
}
