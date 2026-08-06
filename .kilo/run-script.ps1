$ErrorActionPreference = 'Stop'

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP CLI is required to run the local plugin preview.'
}

& php -S 127.0.0.1:8080 -t .
