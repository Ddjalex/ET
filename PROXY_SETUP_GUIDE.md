# Ethiopian Proxy Setup Guide

## Overview

This guide explains how to configure the proxy infrastructure for fetching payment receipts from TeleBirr, CBE, M-Pesa, and other Ethiopian payment providers. The proxy system allows you to route receipt fetch requests through servers located in Ethiopia, which is necessary if these services are geo-restricted.

## Why Use a Proxy?

Ethiopian payment providers (TeleBirr, CBE, M-Pesa) may restrict access to receipt URLs based on geographic location. If your server is hosted outside Ethiopia, you'll need a proxy server located in Ethiopia to fetch these receipts.

## Features

✅ **Automatic Proxy Routing** - All receipt fetches automatically use configured proxy
✅ **Multiple Proxy Support** - Primary proxy with multiple fallback options
✅ **Auto-Fallback** - Falls back to direct connection if all proxies fail
✅ **Health Monitoring** - Built-in health check endpoint
✅ **Flexible Configuration** - Supports HTTP, SOCKS4, and SOCKS5 proxies
✅ **Authentication Support** - Works with password-protected proxies

## Configuration

### 1. Basic Setup

Edit your `secrets/.env` file and add these variables:

```ini
# Enable proxy
PROXY_ENABLED="true"

# Proxy server details
PROXY_HOST="your-ethiopian-proxy.com"
PROXY_PORT="8080"

# Proxy type (HTTP, SOCKS4, or SOCKS5)
PROXY_TYPE="HTTP"

# Authentication (if required)
PROXY_AUTH="username:password"
```

### 2. Advanced Configuration

#### Auto-Fallback System

Enable automatic fallback to direct connection if proxy fails:

```ini
PROXY_AUTO_FALLBACK="true"
```

#### Multiple Fallback Proxies

Configure multiple backup proxies (semicolon-separated):

```ini
PROXY_FALLBACK_LIST="proxy1.et:8080|HTTP|user:pass;proxy2.et:1080|SOCKS5;proxy3.et:3128|HTTP"
```

**Format:** `host:port|type|auth`
- `host:port` - Proxy server address (required)
- `type` - HTTP, SOCKS4, or SOCKS5 (optional, defaults to HTTP)
- `auth` - username:password (optional)

#### Custom Health Check URL

Set a custom URL for proxy health testing:

```ini
PROXY_HEALTH_URL="https://transactioninfo.ethiotelecom.et"
```

## Obtaining an Ethiopian Proxy

### Option 1: Cloud Provider with Ethiopian Servers

Deploy a proxy server on Ethiopian cloud infrastructure:

1. **HostSailor Ethiopia** - VPS hosting in Addis Ababa
2. **Linode/DigitalOcean** - Deploy close to Ethiopia
3. **AWS EC2** - Use Africa (Cape Town) region with routing

### Option 2: Commercial Proxy Services

Use a commercial proxy service with Ethiopian endpoints:

1. **Bright Data** (formerly Luminati)
   - Residential proxies in Ethiopia
   - Pay-per-GB pricing
   - Website: https://brightdata.com

2. **Smartproxy**
   - Ethiopian residential IPs
   - Rotating proxies
   - Website: https://smartproxy.com

3. **Proxy-Seller**
   - Ethiopian datacenter proxies
   - Dedicated IPs available
   - Website: https://proxy-seller.com

### Option 3: Self-Hosted Proxy (Recommended for Production)

Deploy your own proxy server in Ethiopia:

#### Using Squid Proxy (HTTP)

1. **Provision a VPS in Ethiopia**
   ```bash
   # Example using DigitalOcean or similar
   ```

2. **Install Squid on Ubuntu/Debian**
   ```bash
   sudo apt update
   sudo apt install squid -y
   ```

3. **Configure Squid** (`/etc/squid/squid.conf`)
   ```conf
   # Allow your Replit server IP
   acl replit_server src YOUR_REPLIT_IP
   http_access allow replit_server
   
   # Basic auth (optional)
   auth_param basic program /usr/lib/squid/basic_ncsa_auth /etc/squid/passwd
   auth_param basic realm Proxy Authentication
   acl authenticated proxy_auth REQUIRED
   http_access allow authenticated
   
   # Proxy port
   http_port 8080
   
   # Logging
   access_log /var/log/squid/access.log
   ```

4. **Create authentication** (optional)
   ```bash
   sudo htpasswd -c /etc/squid/passwd your_username
   ```

5. **Start Squid**
   ```bash
   sudo systemctl restart squid
   sudo systemctl enable squid
   ```

6. **Configure firewall**
   ```bash
   sudo ufw allow 8080/tcp
   ```

#### Using tinyproxy (Lightweight HTTP Proxy)

1. **Install tinyproxy**
   ```bash
   sudo apt install tinyproxy -y
   ```

2. **Configure** (`/etc/tinyproxy/tinyproxy.conf`)
   ```conf
   Port 8888
   Listen 0.0.0.0
   Allow YOUR_REPLIT_IP
   BasicAuth your_username your_password
   ```

3. **Restart service**
   ```bash
   sudo systemctl restart tinyproxy
   ```

#### Using SOCKS5 Proxy (SSH Tunnel)

1. **Create SSH tunnel from Replit to Ethiopian server**
   ```bash
   ssh -D 1080 -f -C -q -N user@ethiopian-server.com
   ```

2. **Configure .env**
   ```ini
   PROXY_ENABLED="true"
   PROXY_HOST="localhost"
   PROXY_PORT="1080"
   PROXY_TYPE="SOCKS5"
   ```

## Testing Your Proxy

### 1. Check Proxy Configuration

Access the health check endpoint:

```bash
curl "https://your-replit-app.repl.co/bot/proxy-health.php?secret=YOUR_ADMIN_SECRET"
```

**Response Example:**
```json
{
  "ok": true,
  "status": "healthy",
  "health": {
    "timestamp": "2025-10-29T12:00:00+00:00",
    "proxy_config": {
      "enabled": true,
      "proxy_host": "proxy.example.et",
      "proxy_port": 8080,
      "proxy_type": "HTTP",
      "has_auth": true,
      "auto_fallback": true,
      "fallback_count": 2
    },
    "proxy_health": {
      "ok": true,
      "enabled": true,
      "proxy": "proxy.example.et:8080",
      "test_url": "https://www.google.com",
      "response_time_ms": 245
    },
    "test_results": {
      "google": {
        "url": "https://www.google.com",
        "ok": true,
        "duration_ms": 234,
        "status": 200,
        "proxy": "proxy.example.et:8080"
      },
      "telebirr": {
        "url": "https://transactioninfo.ethiotelecom.et",
        "ok": true,
        "duration_ms": 456,
        "status": 200,
        "proxy": "proxy.example.et:8080"
      }
    }
  }
}
```

### 2. Test Receipt Verification

Send a TeleBirr receipt URL to your bot and check if it's fetched successfully through the proxy.

## Troubleshooting

### Proxy Connection Failed

**Error:** `Proxy fetch failed: Could not connect to proxy`

**Solutions:**
1. Verify proxy host and port are correct
2. Check if proxy server is running
3. Ensure firewall allows connections from your Replit IP
4. Test proxy with curl: `curl -x http://proxy:port https://google.com`

### Authentication Failed

**Error:** `407 Proxy Authentication Required`

**Solutions:**
1. Verify PROXY_AUTH format: `username:password`
2. Check credentials are correct on proxy server
3. Ensure proxy server has authentication enabled

### Geo-Restriction Still Blocking

**Error:** Receipt fetch returns 403 or geo-block message

**Solutions:**
1. Verify proxy is actually located in Ethiopia
2. Check proxy IP with: `curl -x http://proxy:port https://ipinfo.io`
3. Some services may block datacenter IPs - use residential proxies

### Slow Response Times

**Issue:** Receipt verification takes too long

**Solutions:**
1. Choose a proxy with lower latency
2. Increase timeout in receipt verification
3. Use multiple fallback proxies
4. Consider using a CDN-based proxy service

### Proxy Health Check Fails

**Error:** Health check returns unhealthy status

**Solutions:**
1. Check proxy server logs
2. Verify proxy has internet connectivity
3. Test with simple URL: `PROXY_HEALTH_URL="https://www.google.com"`
4. Review proxy server firewall rules

## Security Best Practices

1. **Never commit proxy credentials** to version control
2. **Use strong authentication** for your proxy server
3. **Restrict access** to your Replit server IP only
4. **Enable SSL/TLS** for sensitive data
5. **Rotate credentials** regularly
6. **Monitor proxy logs** for suspicious activity
7. **Use dedicated proxies** for production (not shared)

## Cost Estimation

### Self-Hosted Proxy
- **Ethiopian VPS:** $10-30/month
- **Bandwidth:** Included (usually 1-5TB)
- **Setup Time:** 30-60 minutes
- **Best for:** Production use, high volume

### Commercial Proxy Services
- **Residential Proxies:** $500-2000/month (pay-per-GB)
- **Datacenter Proxies:** $50-200/month
- **Setup Time:** 5-10 minutes
- **Best for:** Quick setup, testing

## Performance Optimization

1. **Use HTTP proxies** for better performance (vs SOCKS)
2. **Enable auto-fallback** to minimize downtime
3. **Configure multiple fallback proxies** for redundancy
4. **Monitor response times** and switch to faster proxies
5. **Use proxies close to payment provider servers**

## Integration with Receipt Verification

The proxy system is automatically integrated with the receipt verification system:

1. **Automatic Detection** - When proxy is enabled, all receipt fetches use it
2. **Transparent Operation** - No code changes needed
3. **Fallback Support** - Falls back to direct if proxy fails
4. **Performance Tracking** - Tracks fetch times and proxy usage

## Monitoring

Monitor proxy usage through:

1. **Health endpoint:** `/bot/proxy-health.php`
2. **Receipt verification logs** - Check for `via_proxy: true`
3. **Admin panel** - View successful/failed verifications
4. **Proxy server logs** - Monitor bandwidth and requests

## Support

For proxy setup assistance:
- Check Replit community forums
- Review StroWallet API documentation
- Contact your proxy service provider
- Ethiopian hosting provider support

## Example Configuration

### Production Setup (Self-Hosted)

```ini
PROXY_ENABLED="true"
PROXY_HOST="proxy.myethiopianserver.et"
PROXY_PORT="8080"
PROXY_TYPE="HTTP"
PROXY_AUTH="botuser:SecurePassword123!"
PROXY_AUTO_FALLBACK="true"
PROXY_FALLBACK_LIST="backup1.et:8080|HTTP|user:pass;backup2.et:3128|HTTP"
PROXY_HEALTH_URL="https://transactioninfo.ethiotelecom.et"
```

### Development Setup (Commercial Service)

```ini
PROXY_ENABLED="true"
PROXY_HOST="gw.brightdata.com"
PROXY_PORT="22225"
PROXY_TYPE="HTTP"
PROXY_AUTH="customer-hl_12345678-country-et:api_key"
PROXY_AUTO_FALLBACK="true"
PROXY_HEALTH_URL="https://www.google.com"
```

---

**Last Updated:** October 29, 2025
**Version:** 1.0
