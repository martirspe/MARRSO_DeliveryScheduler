# MARRSO DeliveryScheduler

**Enterprise-Grade Delivery Scheduling System for Magento 2**

An advanced module implementing professional delivery scheduling with support for pickup points and home delivery, similar to checkout flows found in e-commerce platforms like Falabella.

## Features

### Core Functionality

#### Pickup Points
- Display available pickup locations with geolocation support
- Time slot availability by location
- Distance calculation (Haversine formula)
- Capacity management per slot
- Cutoff hour enforcement
- Holiday exclusion
- SLA-based preparation days
- Priority and sorting by distance

#### Home Delivery
- Available delivery slots by district
- Time window selection
- Dynamic pricing
- Capacity management
- District-based coverage rules
- Same-day and next-day delivery support
- Cutoff hour restrictions
- Holiday and weekend exclusions

#### Checkout Integration
- Modern Falabella-style UI
- KnockoutJS components
- AJAX-based slot loading
- Radio button selection
- Responsive design
- Mobile-friendly interface
- Real-time availability updates
- Delivery instructions capture

#### Admin Management
- CRUD interfaces for all entities
- Automatic slot generation via cron
- Configuration management
- Holiday management
- Bulk operations
- Grid filtering and searching

## Architecture

The module follows Magento 2 enterprise patterns:

```
app/code/MARRSO/DeliveryScheduler/
├── Api/                          # Service Contracts & Data Interfaces
├── Block/                         # View blocks
├── Controller/                    # Admin controllers
├── Cron/                         # Scheduled tasks
├── etc/                          # Configuration files
├── Model/                        # Domain models
│   ├── Repository/               # Repository implementations
│   ├── ResourceModel/            # ORM layer
│   ├── Service/                  # Business logic
│   └── Config/                   # Configuration provider
├── Observer/                     # Event listeners
├── Plugin/                       # Plugins/interceptors
├── Ui/                          # UI Components
└── view/                        # Frontend assets
    ├── frontend/                # Storefront
    └── adminhtml/               # Admin panel
```

### Key Components

#### Service Contracts (`Api/`)

**GetPickupLocationsInterface**
```php
public function execute(
    ?string $district = null,
    ?float $customerLatitude = null,
    ?float $customerLongitude = null,
    ?string $date = null
): array
```

**GetDeliverySlotsInterface**
```php
public function execute(
    string $district,
    ?string $startDate = null,
    ?string $endDate = null
): array
```

**SaveDeliverySelectionInterface**
```php
public function execute(
    OrderDeliveryScheduleInterface $deliverySelection
): bool
```

#### Models

**PickupLocation** - Physical pickup point
**PickupSlot** - Time slot at a pickup location
**DeliverySlot** - Time window for home delivery
**Holiday** - Non-operating dates
**OrderDeliverySchedule** - Customer selection persistence

#### Core Services

**AvailabilityEngine** - Calculates available slots based on:
- Active status
- Capacity constraints
- Holiday exclusions
- Weekend filters
- Cutoff hours
- SLA requirements

**DistanceCalculator** - Haversine formula for geographic distance

**ConfigProvider** - Centralized configuration access

## Installation

### Prerequisites
- Magento 2.4.6, 2.4.7, or 2.4.8
- PHP 8.1+
- MySQL 5.7+
- MSI compatibility (optional)

### Steps

1. **Copy Module**
   ```bash
   cp -r DeliveryScheduler app/code/MARRSO/
   ```

2. **Install Magento Module**
   ```bash
   bin/magento module:enable MARRSO_DeliveryScheduler
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento setup:static-content:deploy
   ```

3. **Enable Module**
   ```
   Admin → Stores → Configuration → MARRSO → Delivery Scheduler → General → Enable
   ```

4. **Configure Module**
   - Set cutoff hours
   - Configure capacities
   - Set generation days ahead
   - Configure SLA and holidays

5. **Setup Cron**
   - Ensure Magento cron is running
   - Slots generate automatically every day at midnight

## Configuration

### System Configuration

**Location**: `Stores → Configuration → MARRSO → Delivery Scheduler`

#### General Settings
- **Enable Module** - Master on/off switch
- **Enable Pickup** - Enable pickup point functionality
- **Enable Delivery** - Enable home delivery functionality
- **Default Timezone** - System timezone for calculations

#### Pickup Configuration
- **Cutoff Hour** - Time after which same-day isn't available
- **Preparation Days** - Business days for order fulfillment (SLA)
- **Default Capacity** - Orders per slot
- **Disable Weekends** - No pickup on Saturdays/Sundays

#### Delivery Configuration
- **Cutoff Hour** - Last hour for next-day delivery
- **Enable Same-Day** - Allow same-day delivery
- **Same-Day Cutoff** - Earliest cutoff for same-day
- **Default Price** - Base delivery cost
- **Default Capacity** - Orders per slot
- **Disable Weekends** - No delivery on Saturdays/Sundays

#### Slot Generation
- **Days Ahead** - Pre-generate slots X days in advance
- **Slot Interval** - Duration of each time window (minutes)
- **Start Time** - First slot start time daily
- **End Time** - Last slot end time daily

#### Advanced
- **Enable Debug Logging** - Log all operations
- **Cache Lifetime** - Availability cache duration (seconds)
- **Max Distance** - Filter pickup by distance (km, 0=unlimited)

## Database Schema

### Tables

**marrso_pickup_location**
- `entity_id` (PK)
- `name` - Location name
- `code` - Unique code
- `address` - Physical address
- `district` - Zone/district
- `latitude` - GPS coordinate
- `longitude` - GPS coordinate
- `priority` - Sort order
- `is_active` - Active status
- Timestamps

**marrso_pickup_slot**
- `entity_id` (PK)
- `pickup_location_id` (FK)
- `slot_date` - Date (Y-m-d)
- `start_time` - HH:MM:SS
- `end_time` - HH:MM:SS
- `capacity` - Max orders
- `used_capacity` - Current orders
- `is_active` - Status
- Timestamps

**marrso_delivery_slot**
- `entity_id` (PK)
- `district` - Delivery zone
- `slot_date` - Date (Y-m-d)
- `start_time` - HH:MM:SS
- `end_time` - HH:MM:SS
- `price` - Delivery cost
- `capacity` - Max orders
- `used_capacity` - Current orders
- `carrier_code` - Shipping method
- `is_active` - Status
- Timestamps

**marrso_delivery_holiday**
- `entity_id` (PK)
- `holiday_date` - Excluded date (Y-m-d)
- `description` - Holiday name
- Timestamps

**marrso_order_delivery_schedule**
- `entity_id` (PK)
- `order_id` (FK) - Sales order
- `quote_id` - Quote ID
- `delivery_type` - 'pickup' or 'delivery'
- `pickup_location_id` - Selected pickup
- `delivery_date` - Selected date
- `delivery_slot` - Time window
- `customer_comment` - Instructions
- Timestamps

## REST API

### Get Pickup Locations
```
GET /V1/delivery/pickup-locations
Query Parameters:
  - district (string, optional)
  - latitude (float, optional)
  - longitude (float, optional)
  - date (string, optional, Y-m-d format)

Response:
{
  "items": [
    {
      "entity_id": 1,
      "name": "Jockey Plaza",
      "address": "Av. Paseo de la República 3520",
      "district": "San Isidro",
      "latitude": -12.1234,
      "longitude": -77.0456,
      "distance_km": 2.5,
      "priority": 0,
      "slots": [
        {
          "entity_id": 10,
          "date": "2026-05-20",
          "start_time": "09:00",
          "end_time": "11:00",
          "available_spots": 5
        }
      ]
    }
  ]
}
```

### Get Delivery Slots
```
GET /V1/delivery/delivery-slots
Query Parameters:
  - district (string, required)
  - start_date (string, optional, Y-m-d)
  - end_date (string, optional, Y-m-d)

Response:
{
  "items": [
    {
      "entity_id": 1,
      "district": "San Isidro",
      "date": "2026-05-20",
      "start_time": "09:00",
      "end_time": "12:00",
      "price": 9.90,
      "available_spots": 8,
      "carrier_code": "standard"
    }
  ]
}
```

### Save Delivery Selection
```
POST /V1/delivery/save

Request Body:
{
  "deliverySelection": {
    "order_id": 123,
    "quote_id": 456,
    "delivery_type": "pickup",
    "pickup_location_id": 1,
    "delivery_date": "2026-05-20",
    "delivery_slot": "09:00-11:00",
    "customer_comment": "Please ring doorbell twice"
  }
}

Response:
{
  "success": true
}
```

## Frontend Integration

### Checkout Component

The module automatically registers the `delivery-scheduler` component in the checkout shipping step.

**JavaScript Configuration**:
```javascript
// Automatically configured in jsLayout
window.marrsoDeliverySchedulerConfig = {
  enabled: true,
  pickupEnabled: true,
  deliveryEnabled: true
};
```

### KnockoutJS Component

The component (`MARRSO_DeliveryScheduler/js/view/delivery-scheduler`) provides:

- Tab switching between Pickup/Delivery
- Real-time slot loading via AJAX
- Distance calculation display
- Capacity indicators
- Price display
- Optional delivery instructions
- Selection summary

### Customization

**Override Template**:
```xml
<!-- Create in your theme -->
app/design/frontend/YOUR_VENDOR/YOUR_THEME/MARRSO_DeliveryScheduler/template/delivery-scheduler.html
```

**Override JavaScript**:
```javascript
// In requirejs-config.js
var config = {
  paths: {
    'MARRSO_DeliveryScheduler/js/view/delivery-scheduler': 
      'js/your-custom-delivery-scheduler'
  }
};
```

## Admin Interface

### Pickup Locations Management
- Create/Edit/Delete locations
- Set coordinates for distance calculation
- Assign to districts
- Set priority and capacity
- View available slots

### Delivery Slots Management
- Create/Edit/Delete slots
- Define time windows
- Set district coverage
- Configure pricing
- Manage capacity

### Holiday Management
- Add non-operating dates
- Bulk import from calendar
- Exclude from all slot types

### Configuration
- All system settings
- Enable/disable features
- Set business rules
- Configure SLA

## Cron Jobs

### Generate Slots (Daily 00:00)
- Automatically creates pickup and delivery slots
- Respects configuration settings
- Avoids duplicates
- Honors holidays and weekends

### Cleanup Expired (Daily 01:00)
- Removes past slots
- Prevents data accumulation
- Maintains database performance

## Development

### Project Structure Follows
- **PSR-12** - Code standards
- **SOLID** - Design principles
- **Magento 2 Best Practices** - Enterprise patterns
- **Semantic Versioning** - Version scheme

### Running Tests

```bash
# Unit tests
vendor/bin/phpunit tests/Unit

# Integration tests
vendor/bin/phpunit tests/Integration

# Code standards
vendor/bin/phpmd app/code/MARRSO/DeliveryScheduler text codesize,naming,unusedcode
```

### Key Classes

- `AvailabilityEngine` - Core business logic
- `DistanceCalculator` - Geographic calculations
- `ConfigProvider` - Configuration access
- Repositories - CRUD operations
- Resource Models - ORM layer

## Performance Optimization

### Caching
- Configuration values cached
- Availability data cached per request
- Extension attributes lazy loaded

### Database
- Proper indexes on frequent queries
- Foreign key relationships
- Optimized collections

### Frontend
- AJAX-based loading (non-blocking)
- Lazy component initialization
- Minimal JS bundle
- CSS scoped to component

## Troubleshooting

### Slots Not Generating
1. Check if cron is running: `php bin/magento cron:run`
2. Verify configuration: `Stores → Configuration → MARRSO → Delivery Scheduler`
3. Check logs: `var/log/system.log` and `var/log/marrso_delivery_scheduler.log`

### Frontend Component Not Showing
1. Run setup upgrade: `bin/magento setup:upgrade`
2. Recompile: `bin/magento setup:di:compile`
3. Deploy static: `bin/magento setup:static-content:deploy`
4. Clear cache: `bin/magento cache:clean`

### Capacity Not Working
1. Verify capacity value > 0 in configuration
2. Check used_capacity field in database
3. Ensure orders increment used_capacity

### Distance Calculation Issues
1. Verify locations have latitude/longitude
2. Check max_distance configuration (0 = unlimited)
3. Ensure customer address has district

## Support & Maintenance

### Regular Maintenance
- Monitor slot generation cron execution
- Clean up old expired slots
- Review error logs monthly
- Verify database indexes

### Upgrades
- Always backup database before upgrades
- Test in staging environment
- Run setup:upgrade after deployment
- Recompile and deploy static files

## License

OSL-3.0

## Author

MARRSO - Enterprise Magento Development

---

**Version**: 1.0.0
**Last Updated**: May 2026
**Compatibility**: Magento 2.4.6+, PHP 8.1+
