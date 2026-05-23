# MARRSO DeliveryScheduler - Installation & Configuration Guide

## Quick Start

### 1. Installation

```bash
# Copy the module to app/code
mkdir -p app/code/MARRSO
cp -r DeliveryScheduler app/code/MARRSO/

# Enable the module
php bin/magento module:enable MARRSO_DeliveryScheduler

# Run setup upgrade
php bin/magento setup:upgrade

# Compile DI
php bin/magento setup:di:compile

# Deploy static content
php bin/magento setup:static-content:deploy -f

# Clear cache
php bin/magento cache:clean
```

### 2. Basic Configuration

Navigate to: **Stores → Configuration → MARRSO → Delivery Scheduler**

#### Enable the Module
1. Go to **General Settings**
2. Set **Enable Delivery Scheduler** = Yes
3. Set **Enable Pickup Points** = Yes (if using pickup)
4. Set **Enable Home Delivery** = Yes (if using delivery)
5. Set **Default Timezone** = Your store timezone

#### Configure Pickup
1. Go to **Pickup Points Configuration**
2. Set **Cutoff Hour** = 17 (5 PM)
3. Set **Preparation Days** = 1 (minimum business days)
4. Set **Default Slot Capacity** = 10 (orders per slot)
5. Enable/disable weekends as needed

#### Configure Delivery
1. Go to **Home Delivery Configuration**
2. Set **Cutoff Hour** = 17
3. Enable **Same-Day Delivery** if desired
4. Set **Same-Day Cutoff Hour** = 14 (2 PM)
5. Set **Default Delivery Price** = 9.90
6. Set **Default Slot Capacity** = 20
7. Enable/disable weekends

#### Slot Generation
1. Go to **Slot Generation**
2. Set **Generate Slots X Days Ahead** = 30
3. Set **Slot Interval (Minutes)** = 120 (2-hour slots)
4. Set **Daily Start Time** = 09:00
5. Set **Daily End Time** = 18:00

#### Advanced Settings
1. Go to **Advanced Settings**
2. Enable **Enable Debug Logging** if needed
3. Set **Cache Lifetime** = 3600 (1 hour)
4. Set **Max Distance (km)** = 0 (unlimited, or set distance filter)

### 3. Add Pickup Locations

Navigate to: **MARRSO → Pickup Locations**

#### Create New Location
1. Click **Add Pickup Location**
2. Fill in:
   - **Name**: e.g., "Jockey Plaza"
   - **Code**: e.g., "jockey_plaza" (unique identifier)
   - **Address**: Full physical address
   - **District**: Zone/region
   - **Latitude**: GPS latitude
   - **Longitude**: GPS longitude
   - **Priority**: 0 (higher priority shows first)
   - **Is Active**: Checked
3. Click **Save Location**

**Note**: Latitude/Longitude needed for distance calculations:
```
Jockey Plaza: -12.120769, -77.048084
San Isidro: -12.099400, -77.034450
Miraflores: -12.117606, -77.028908
```

### 4. Add Delivery Slots

Navigate to: **MARRSO → Delivery Slots**

#### Create New Slot
1. Click **Add Delivery Slot**
2. Fill in:
   - **District**: Delivery zone
   - **Delivery Date**: YYYY-MM-DD format
   - **Start Time**: HH:MM (24-hour)
   - **End Time**: HH:MM (24-hour)
   - **Price**: Cost (can be 0 for free)
   - **Capacity**: Max orders in this slot
   - **Carrier Code**: Shipping method code (optional)
   - **Is Active**: Checked
3. Click **Save Slot**

**Example**:
- District: San Isidro
- Date: 2026-05-20
- Time: 09:00 - 12:00
- Price: 9.90
- Capacity: 15

### 5. Add Holidays

Navigate to: **MARRSO → Holidays**

#### Create New Holiday
1. Click **Add Holiday**
2. Fill in:
   - **Holiday Date**: YYYY-MM-DD format
   - **Description**: Holiday name
3. Click **Save Holiday**

**Example**:
- Date: 2026-07-28
- Description: Peruvian Independence Day

### 6. Verify Automatic Slot Generation

The module generates slots automatically via cron (daily at midnight).

To manually trigger:
```bash
# Run cron job manually
php bin/magento cron:run

# Or specifically
php bin/magento cron:run --group default
```

Check generated slots in **MARRSO → Pickup Slots** (auto-generated from locations).

## Testing

### Test Pickup Flow

1. Create a test pickup location
2. Add test pickup slots for next 10 days
3. Go to checkout as customer
4. Select shipping method
5. Verify delivery scheduler appears
6. Click "Pickup Point" tab
7. Select a location
8. Verify selection is saved

### Test Delivery Flow

1. Create test delivery slots for your district
2. Set district in customer address
3. Go to checkout
4. Click "Home Delivery" tab
5. Select a slot
6. Verify selection is saved

### API Testing

```bash
# Test Pickup Locations API
curl -X GET "http://your-store.local/rest/V1/delivery/pickup-locations?district=SanIsidro"

# Test Delivery Slots API
curl -X GET "http://your-store.local/rest/V1/delivery/delivery-slots?district=SanIsidro"

# Test Save Delivery Selection
curl -X POST "http://your-store.local/rest/V1/delivery/save" \
  -H "Content-Type: application/json" \
  -d '{
    "deliverySelection": {
      "quote_id": 1,
      "delivery_type": "pickup",
      "pickup_location_id": 1,
      "delivery_date": "2026-05-20"
    }
  }'
```

## Common Tasks

### Generate Slots for Next 30 Days

```bash
# Edit configuration to generate 30 days ahead
# Then run cron or wait for midnight
php bin/magento cron:run
```

### Bulk Import Holidays

Create CSV file with columns:
- holiday_date (YYYY-MM-DD)
- description (text)

Then import via:
1. Admin → System → Data Transfer → Import
2. Import Settings → Entity Type → Holiday
3. Upload CSV file

### Monitor Slot Generation

Check logs:
```bash
tail -f var/log/marrso_delivery_scheduler.log
```

### Performance Tuning

```bash
# Reindex if needed
php bin/magento indexer:reindex

# Optimize database
php bin/magento db:upgrade

# Clear cache if changes aren't visible
php bin/magento cache:clean
```

## Troubleshooting

### Issue: Delivery Scheduler Not Showing in Checkout

**Solution**:
1. Verify module is enabled: `php bin/magento module:status | grep MARRSO`
2. Check if enabled in config: **Stores → Configuration → MARRSO → General → Enable**
3. Recompile: `php bin/magento setup:di:compile`
4. Deploy static: `php bin/magento setup:static-content:deploy -f`
5. Clear cache: `php bin/magento cache:clean`
6. Hard refresh browser (Ctrl+Shift+R)

### Issue: No Slots Appearing

**Solution**:
1. Verify cron is running: `grep marrso var/log/system.log`
2. Check logs: `tail -f var/log/marrso_delivery_scheduler.log`
3. Manually generate: `php bin/magento cron:run --group default`
4. Verify locations exist: **MARRSO → Pickup Locations**
5. Verify configuration: Check **Slot Generation** settings
6. Check holidays: Make sure date isn't a holiday

### Issue: Distance Not Calculating

**Solution**:
1. Verify location has latitude/longitude
2. Check customer address has district
3. Verify **Max Distance** in config (0 = unlimited)
4. Enable logging to debug: **Stores → Configuration → MARRSO → Advanced → Enable Debug Logging**

### Issue: Slots Not Reserving/Capacity Not Working

**Solution**:
1. Verify capacity > 0 in slot configuration
2. Check database: `SELECT * FROM marrso_pickup_slot WHERE entity_id = X`
3. Verify used_capacity < capacity for slot
4. Check order save observer: **etc/events.xml**

### Issue: API Returns Empty Results

**Solution**:
1. Verify data exists in database
2. Check district/location parameters match
3. Enable query logging:
   ```bash
   # In app/etc/env.php add:
   'db' => [
     'log_all_queries' => 1
   ]
   ```
4. Check var/log/db.log for queries

## Database Verification

```sql
-- Check locations
SELECT * FROM marrso_pickup_location;

-- Check pickup slots
SELECT * FROM marrso_pickup_slot WHERE slot_date >= CURDATE();

-- Check delivery slots
SELECT * FROM marrso_delivery_slot WHERE slot_date >= CURDATE();

-- Check holidays
SELECT * FROM marrso_delivery_holiday WHERE holiday_date >= CURDATE();

-- Check order scheduling
SELECT * FROM marrso_order_delivery_schedule;

-- Check slot capacity
SELECT entity_id, slot_date, capacity, used_capacity, 
       (capacity - used_capacity) as available
FROM marrso_pickup_slot
WHERE slot_date >= CURDATE()
ORDER BY slot_date, start_time;
```

## Support

For issues or questions:
1. Check error logs: `var/log/system.log` and `var/log/marrso_delivery_scheduler.log`
2. Enable debug logging in configuration
3. Test with clean database tables
4. Verify PHP version: `php -v` (requires 8.1+)
5. Verify Magento version: **Magento 2.4.6+**

---

**Last Updated**: May 2026
**Version**: 1.0.0
