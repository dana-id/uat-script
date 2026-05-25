import * as dotenv from 'dotenv';
import * as path from 'path';
import { ensureMerchantBniVaTopUp } from '../helper/merchantBniVaTopUp';

dotenv.config({ path: path.resolve(__dirname, '../../../.env') });

let topUpStarted = false;

beforeAll(async () => {
  const testPath = expect.getState().testPath || '';
  if (!testPath.includes(`${path.sep}disbursement${path.sep}`)) {
    return;
  }
  if (topUpStarted) {
    return;
  }
  topUpStarted = true;
  await ensureMerchantBniVaTopUp();
}, 120000);
