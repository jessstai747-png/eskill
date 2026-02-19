#!/bin/bash
# ============================================
# Cleanup Script - Remove Non-SEO Files
# ============================================
# This script removes files not related to SEO functionality
# Run with --dry-run first to see what will be deleted
#
# Usage: ./scripts/cleanup-non-seo.sh [--dry-run]
# ============================================

set -e

DRY_RUN=false
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
    echo "=== DRY RUN MODE - No files will be deleted ==="
fi

BASE_DIR="/home/eskill/htdocs/eskill.com.br"
cd "$BASE_DIR"

REMOVED=0
ERRORS=0

remove_file() {
    local file="$1"
    if [[ -f "$file" ]]; then
        if $DRY_RUN; then
            echo "[DRY-RUN] Would remove: $file"
        else
            rm -f "$file" && echo "Removed: $file" || ((ERRORS++))
        fi
        ((REMOVED++))
    fi
}

remove_dir() {
    local dir="$1"
    if [[ -d "$dir" ]]; then
        if $DRY_RUN; then
            echo "[DRY-RUN] Would remove directory: $dir"
        else
            rm -rf "$dir" && echo "Removed directory: $dir" || ((ERRORS++))
        fi
        ((REMOVED++))
    fi
}

echo ""
echo "=========================================="
echo "  NON-SEO FILE CLEANUP"
echo "=========================================="
echo ""

# ============================================
# CONTROLLERS - Non-SEO
# ============================================
echo "--- Controllers (Non-SEO) ---"

# Marketplace/Orders
remove_file "app/Controllers/OrderController.php"
remove_file "app/Controllers/OrdersController.php"
remove_file "app/Controllers/MercadoLivreWebhookController.php"
remove_file "app/Controllers/ShopeeController.php"

# Inventory
remove_file "app/Controllers/InventoryAdvancedController.php"

# Shipping
remove_file "app/Controllers/ShippingController.php"
remove_file "app/Controllers/ShippingAdvancedController.php"

# Financial
remove_file "app/Controllers/SettlementController.php"
remove_file "app/Controllers/FinancialReportController.php"

# Pricing/Promotions
remove_file "app/Controllers/DynamicPricingController.php"
remove_file "app/Controllers/PromotionController.php"
remove_file "app/Controllers/PromotionAdvancedController.php"

# Returns/Claims
remove_file "app/Controllers/ReturnController.php"
remove_file "app/Controllers/ClaimsController.php"
remove_file "app/Controllers/QuestionController.php"

# Ads
remove_file "app/Controllers/AdsController.php"

# Customer/Account
remove_file "app/Controllers/CustomerController.php"
remove_file "app/Controllers/UserProductsController.php"

# Messaging
remove_file "app/Controllers/MessageController.php"
remove_file "app/Controllers/MessagingController.php"
remove_file "app/Controllers/WhatsAppController.php"

# Other
remove_file "app/Controllers/PublicProductController.php"
remove_file "app/Controllers/CatalogCloneController.php"
remove_file "app/Controllers/EanController.php"
remove_file "app/Controllers/PushController.php"
remove_file "app/Controllers/RealTimeNotificationController.php"
remove_file "app/Controllers/WebhookController.php"
remove_file "app/Controllers/SyncController.php"
remove_file "app/Controllers/ExportController.php"
remove_file "app/Controllers/PollingController.php"
remove_file "app/Controllers/FlexController.php"
remove_file "app/Controllers/FullController.php"
remove_file "app/Controllers/CompatibilityController.php"

# ============================================
# SERVICES - Non-SEO
# ============================================
echo ""
echo "--- Services (Non-SEO) ---"

# Marketplace
remove_file "app/Services/OrderService.php"
remove_file "app/Services/MercadoLivreClient.php"
remove_file "app/Services/MercadoLivreAuthService.php"
remove_file "app/Services/ShopeeService.php"
remove_file "app/Services/UnifiedOrderService.php"

# Inventory
remove_file "app/Services/InventoryService.php"
remove_file "app/Services/InventoryAutoManager.php"
remove_file "app/Services/ItemService.php"
remove_file "app/Services/ItemMetricsService.php"

# Shipping
remove_file "app/Services/ShippingService.php"
remove_file "app/Services/FulfillmentService.php"

# Pricing/Financial
remove_file "app/Services/DynamicPricingService.php"
remove_file "app/Services/PricingAutoOptimizer.php"
remove_file "app/Services/SettlementService.php"
remove_file "app/Services/PromotionService.php"

# Customer Service
remove_file "app/Services/QuestionService.php"
remove_file "app/Services/NegotiationService.php"
remove_file "app/Services/ReputationService.php"

# Messaging
remove_file "app/Services/MessagingService.php"
remove_file "app/Services/PushNotificationService.php"
remove_file "app/Services/WhatsAppService.php"

# Catalog
remove_file "app/Services/CatalogCloneService.php"
remove_file "app/Services/ListingBuilderService.php"
remove_file "app/Services/ListingAutoCreator.php"

# User Products
remove_file "app/Services/UserProductsService.php"

# Search (Non-SEO)
remove_file "app/Services/AlternativeSearchService.php"

# Other
remove_file "app/Services/AdsService.php"
remove_file "app/Services/OpportunityDetectorService.php"

# ============================================
# MODELS - Non-SEO (EAN)
# ============================================
echo ""
echo "--- Models (EAN-related) ---"

remove_file "app/Models/EanAssignment.php"
remove_file "app/Models/EanBalance.php"
remove_file "app/Models/EanInventory.php"
remove_file "app/Models/EanPackage.php"
remove_file "app/Models/EanPurchase.php"
remove_file "app/Models/EanTransaction.php"

# ============================================
# VIEWS - Non-SEO
# ============================================
echo ""
echo "--- Views (Non-SEO) ---"

remove_dir "app/Views/dashboard/orders"
remove_file "app/Views/dashboard/orders-content.php"
remove_file "app/Views/dashboard/shipping.php"
remove_dir "app/Views/dashboard/shipping"
remove_dir "app/Views/dashboard/returns"
remove_dir "app/Views/dashboard/claims"
remove_dir "app/Views/dashboard/pricing"
remove_dir "app/Views/dashboard/inventory"
remove_dir "app/Views/dashboard/marketing"
remove_dir "app/Views/dashboard/promotions"
remove_dir "app/Views/dashboard/ads"
remove_file "app/Views/dashboard/whatsapp.php"
remove_file "app/Views/dashboard/financials.php"
remove_file "app/Views/dashboard/compatibility.php"
remove_file "app/Views/catalog/clone.php"
remove_file "app/Views/dashboard/ean-admin.php"
remove_dir "app/Views/dashboard/customers"
remove_dir "app/Views/dashboard/shopee"

# ============================================
# SUMMARY
# ============================================
echo ""
echo "=========================================="
echo "  CLEANUP SUMMARY"
echo "=========================================="
echo "Files/Directories processed: $REMOVED"
echo "Errors: $ERRORS"

if $DRY_RUN; then
    echo ""
    echo "This was a DRY RUN. To actually delete files, run:"
    echo "  ./scripts/cleanup-non-seo.sh"
fi

echo ""
echo "Done!"
