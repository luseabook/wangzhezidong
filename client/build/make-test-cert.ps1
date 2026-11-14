param(
  [string]$Subject = "CN=Wangzhe Test",
  [Parameter(Mandatory = $true)][string]$Password,
  [switch]$Trust
)

$ErrorActionPreference = 'Stop'
$OutDir = Join-Path $PSScriptRoot '..\..\..\certs'
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

$cert = New-SelfSignedCertificate `
  -Type CodeSigningCert `
  -Subject $Subject `
  -CertStoreLocation 'Cert:\CurrentUser\My' `
  -KeyExportPolicy Exportable `
  -KeyUsage DigitalSignature `
  -KeySpec Signature `
  -KeyLength 2048 `
  -HashAlgorithm SHA256 `
  -NotAfter (Get-Date).AddYears(5)

$secure = ConvertTo-SecureString $Password -AsPlainText -Force
$pfx = Join-Path $OutDir 'wangzhe-test-codesign.pfx'
$cer = Join-Path $PSScriptRoot 'wangzhe-test-codesign.cer'
Export-PfxCertificate -Cert $cert -FilePath $pfx -Password $secure | Out-Null
Export-Certificate -Cert $cert -FilePath $cer | Out-Null

if ($Trust) {
  Import-Certificate -FilePath $cer -CertStoreLocation 'Cert:\CurrentUser\Root' | Out-Null
  Import-Certificate -FilePath $cer -CertStoreLocation 'Cert:\CurrentUser\TrustedPublisher' | Out-Null
}

Write-Host "SUBJECT=$($cert.Subject)"
Write-Host "THUMBPRINT=$($cert.Thumbprint)"
Write-Host "PFX=$pfx"
Write-Host "CER=$cer"
