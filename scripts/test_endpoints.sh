#!/bin/bash

# StroWallet API Endpoints Test Script
# Tests connectivity and authentication with your API keys

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=================================="
echo "  StroWallet API Endpoint Tester"
echo "=================================="
echo ""

# Load environment variables
ENV_FILE="../secrets/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}Error: .env file not found at $ENV_FILE${NC}"
    echo "Please copy secrets/.env.example to secrets/.env and configure it."
    exit 1
fi

# Parse .env file
export $(grep -v '^#' "$ENV_FILE" | xargs)

# Check required variables
if [ -z "$STROW_BASE" ] || [ -z "$STROW_ADMIN_KEY" ] || [ -z "$STROW_PERSONAL_KEY" ]; then
    echo -e "${RED}Error: Missing required environment variables${NC}"
    echo "Please ensure STROW_BASE, STROW_ADMIN_KEY, and STROW_PERSONAL_KEY are set in .env"
    exit 1
fi

echo "Base URL: $STROW_BASE"
echo ""

# Test 1: Fetch Cards (Admin Key)
echo -e "${YELLOW}Test 1: Fetching cards (Admin Key)...${NC}"
RESPONSE=$(curl -s -w "\n%{http_code}" -X GET \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $STROW_ADMIN_KEY" \
    "$STROW_BASE/bitvcard/fetch-card-detail/")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Success (HTTP $HTTP_CODE)${NC}"
    echo "Response: $BODY" | jq '.' 2>/dev/null || echo "$BODY"
else
    echo -e "${RED}✗ Failed (HTTP $HTTP_CODE)${NC}"
    echo "Response: $BODY"
fi
echo ""

# Test 2: Get Profile/Balance (Personal Key)
echo -e "${YELLOW}Test 2: Fetching profile/balance (Personal Key)...${NC}"
RESPONSE=$(curl -s -w "\n%{http_code}" -X GET \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $STROW_PERSONAL_KEY" \
    "$STROW_BASE/user/profile")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Success (HTTP $HTTP_CODE)${NC}"
    echo "Response: $BODY" | jq '.' 2>/dev/null || echo "$BODY"
else
    echo -e "${RED}✗ Failed (HTTP $HTTP_CODE)${NC}"
    echo "Response: $BODY"
fi
echo ""

# Test 3: Get Wallet Balance (Personal Key)
echo -e "${YELLOW}Test 3: Fetching wallet balance (Personal Key)...${NC}"
RESPONSE=$(curl -s -w "\n%{http_code}" -X GET \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $STROW_PERSONAL_KEY" \
    "$STROW_BASE/wallet/balance")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Success (HTTP $HTTP_CODE)${NC}"
    echo "Response: $BODY" | jq '.' 2>/dev/null || echo "$BODY"
else
    echo -e "${RED}✗ Failed (HTTP $HTTP_CODE)${NC}"
    echo "Response: $BODY"
fi
echo ""

echo "=================================="
echo "  Test Complete"
echo "=================================="
echo ""
echo "If any tests failed:"
echo "1. Verify your API keys are correct"
echo "2. Check that the base URL is correct"
echo "3. Ensure your StroWallet account is active"
echo "4. Review the API documentation: https://strowallet.readme.io"
