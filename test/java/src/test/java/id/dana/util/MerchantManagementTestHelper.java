package id.dana.util;

import id.dana.merchantmanagement.v1.model.QueryAssetCardListRequest;
import id.dana.merchantmanagement.v1.model.QueryMerchantInfoRequest;
import java.util.Arrays;

public final class MerchantManagementTestHelper {

  private MerchantManagementTestHelper() {}

  public static QueryMerchantInfoRequest queryMerchantInfoRequest() {
    String roleId = ConfigUtil.getConfig("MERCHANT_ID", "");
    if (roleId.isEmpty()) {
      throw new IllegalStateException("MERCHANT_ID is required to query merchant info");
    }

    QueryMerchantInfoRequest request = new QueryMerchantInfoRequest();
    request.setRoleId(roleId);
    request.setLoginType(QueryMerchantInfoRequest.LoginTypeEnum.ROLE);
    request.setIsQueryAccount(true);
    return request;
  }

  public static QueryAssetCardListRequest queryAssetCardListRequest(String memberId) {
    QueryAssetCardListRequest request = new QueryAssetCardListRequest();
    request.setMemberId(memberId);
    request.setEnableOnly(QueryAssetCardListRequest.EnableOnlyEnum.TRUE);
    request.setAssetTypeList(Arrays.asList(QueryAssetCardListRequest.AssetTypeListEnum.VA_ACCOUNT));
    return request;
  }
}
