param(
  [string]$RenderApiKey = $env:RENDER_API_KEY,
  [string]$ServiceId = "srv-d81t3cf7f7vs73eflp6g",
  [string]$AdminApiKey = "emploi-info-sync-2026"
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($RenderApiKey)) {
  throw "RENDER_API_KEY est requis. Cree une cle API Render temporaire puis relance avec -RenderApiKey ou la variable d'environnement RENDER_API_KEY."
}

$headers = @{
  Authorization = "Bearer $RenderApiKey"
  Accept = "application/json"
  "Content-Type" = "application/json"
}

Write-Host "Configuration de API_KEY sur Render..."
$envBody = @{ value = $AdminApiKey } | ConvertTo-Json
Invoke-RestMethod `
  -Uri "https://api.render.com/v1/services/$ServiceId/env-vars/API_KEY" `
  -Method Put `
  -Headers $headers `
  -Body $envBody | Out-Null

Write-Host "Declenchement du redeploiement Render..."
$deployBody = @{ clearCache = "do_not_clear" } | ConvertTo-Json
$deploy = Invoke-RestMethod `
  -Uri "https://api.render.com/v1/services/$ServiceId/deploys" `
  -Method Post `
  -Headers $headers `
  -Body $deployBody

Write-Host "Deploy lance."
if ($deploy.id) {
  Write-Host "Deploy ID: $($deploy.id)"
}
Write-Host "Attends la fin du deploy dans Render, puis teste /api/v1/admin/offers."
