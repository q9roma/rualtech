$ErrorActionPreference = 'Stop'
Write-Host 'Staging: https://localhost:8443 — keep this terminal open.'
Write-Host 'Uses a private staging certificate; see docs/staging.md.'
& ssh -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 -N -L 127.0.0.1:8443:127.0.0.1:8443 rualtech
exit $LASTEXITCODE
