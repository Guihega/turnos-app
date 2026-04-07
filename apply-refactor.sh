#!/bin/bash
# TurnosPro Phase 1 Refactor — Post-apply script
# Run this AFTER extracting the tar.gz

echo "=== TurnosPro Phase 1 Refactor ==="

# 1. Delete mock dashboard file
if [ -f "resources/js/Pages/dashboard.jsx" ]; then
    rm resources/js/Pages/dashboard.jsx
    echo "✓ Deleted dashboard.jsx (mock file)"
else
    echo "· dashboard.jsx already deleted"
fi

# 2. Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo "✓ Caches cleared"

# 3. Rebuild frontend
npm run build
echo "✓ Frontend rebuilt"

# 4. Verify routes
echo ""
echo "=== Route verification ==="
echo "Web routes with tenant.scope:"
php artisan route:list --path=dashboard 2>/dev/null | head -5
echo ""
echo "Admin routes with role middleware:"
php artisan route:list --path=administracion 2>/dev/null | head -5
echo ""
echo "Kiosk public routes (no auth):"
php artisan route:list --path=kiosco 2>/dev/null | head -5

echo ""
echo "=== Phase 1 Complete ==="
echo "Changes applied:"
echo "  1. tenant.scope middleware on ALL auth web routes"
echo "  2. role:admin middleware on /administracion/*"
echo "  3. role:operator middleware on /atencion/*"
echo "  4. OperatorController refactored to use Actions"
echo "  5. KioskController refactored to use IssueTicketAction"
echo "  6. preventLazyLoading enabled in development"
echo "  7. Broadcasting channels for branch + display"
echo "  8. dashboard.jsx (mock) deleted"
