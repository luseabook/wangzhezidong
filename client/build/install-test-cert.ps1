param(
  [string]$CerPath = (Join-Path $PSScriptRoot 'wangzhe-test-codesign.cer')
)

$ErrorActionPreference = 'Stop'
if (!(Test-Path $CerPath)) {
  throw "证书不存在：$CerPath"
}

Import-Certificate -FilePath $CerPath -CertStoreLocation 'Cert:\CurrentUser\Root' | Out-Null
Import-Certificate -FilePath $CerPath -CertStoreLocation 'Cert:\CurrentUser\TrustedPublisher' | Out-Null

Write-Host "已安装到当前用户的 受信任的根证书颁发机构 和 受信任的发布者："
Write-Host $CerPath
Write-Host "请重新打开安装包；如果仍提示未知发布者，注销/重启后重试。"
