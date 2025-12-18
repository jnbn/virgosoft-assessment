# Limit-Order Exchange Mini Engine

A full-stack limit-order exchange implementation built with Laravel and Vue.js, featuring real-time order matching, balance management, and Pusher integration.

## Technology Stack

- **Backend:** Laravel 12.x
- **Frontend:** Vue.js 3 (Composition API) + Tailwind CSS
- **Database:** MySQL/PostgreSQL
- **Real-time:** Pusher via Laravel Broadcasting
- **Authentication:** Laravel Fortify

## Features

- ✅ Limit order placement (Buy/Sell)
- ✅ Real-time order matching engine
- ✅ Atomic balance and asset management
- ✅ Commission calculation (1.5%)
- ✅ Real-time updates via Pusher
- ✅ Order cancellation
- ✅ Orderbook visualization
- ✅ Wallet overview with USD and asset balances

## Setup Instructions

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL/PostgreSQL
- Pusher account (for real-time features)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd virgosoft
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node dependencies**
   ```bash
   npm install
   ```

4. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Configure your `.env` file with:
   - Database credentials
   - Pusher credentials:
     ```
     BROADCAST_DRIVER=pusher
     PUSHER_APP_ID=your_app_id
     PUSHER_APP_KEY=your_app_key
     PUSHER_APP_SECRET=your_app_secret
     PUSHER_APP_CLUSTER=your_cluster
     
     VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
     VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
     ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Build assets**
   ```bash
   npm run build
   ```

### Development

Run the development server with hot reloading:

```bash
npm run dev
```

Or use the Laravel dev script (includes queue worker and logs):

```bash
composer run dev
```

### Queue Worker

The order matching runs via Laravel queues. Make sure to run the queue worker:

```bash
php artisan queue:work
```

Or use the dev script which includes it automatically.

### Order Matching Command

The `orders:match` command should be called after each buy/sell transaction to process pending orders. There are several ways to trigger it:

1. **Via Artisan Facade** - Call it directly where transactions happen (e.g., in `OrderService` after a transaction commits):
   ```php
   use Illuminate\Support\Facades\Artisan;
   
   Artisan::call('orders:match');
   ```

2. **Manual execution** - Run it manually via command line:
   ```bash
   php artisan orders:match
   ```

3. **Automated via scheduler** - Add it to `app/Console/Kernel.php` schedule method or set up a cron job for periodic batch processing.

Currently, it's set up for manual execution, but it can be integrated into the transaction flow using the Artisan facade for immediate matching after each order placement.

## API Endpoints

All API endpoints require authentication via session-based auth.

### GET `/api/profile`
Returns authenticated user's USD balance and asset balances.

**Response:**
```json
{
  "balance": 10000.00,
  "assets": [
    {
      "symbol": "BTC",
      "amount": 0.5,
      "locked_amount": 0.1,
      "available": 0.4
    }
  ]
}
```

### GET `/api/orders?symbol=BTC`
Returns all open orders for the orderbook (buy & sell orders).

**Response:**
```json
{
  "orders": [
    {
      "id": 1,
      "symbol": "BTC",
      "side": "buy",
      "price": 95000.00,
      "amount": 0.01,
      "user_id": 1,
      "created_at": "2025-12-17T10:00:00Z"
    }
  ]
}
```

### POST `/api/orders`
Creates a new limit order.

**Request Body:**
```json
{
  "symbol": "BTC",
  "side": "buy",
  "price": 95000.00,
  "amount": 0.01
}
```

**Response:**
```json
{
  "message": "Order created successfully",
  "order": {
    "id": 1,
    "symbol": "BTC",
    "side": "buy",
    "price": 95000.00,
    "amount": 0.01,
    "status": 1,
    "created_at": "2025-12-17T10:00:00Z"
  }
}
```

### POST `/api/orders/{id}/cancel`
Cancels an open order and releases locked USD or assets.

**Response:**
```json
{
  "message": "Order cancelled successfully",
  "order": {
    "id": 1,
    "status": 3
  }
}
```

## Real-Time Events

The application broadcasts events via Pusher on private channels:

### `private-user.{userId}`

**Event: `order.matched`**

Broadcasted when an order is matched. Contains:
- Trade details
- Updated buyer/seller balances
- Updated asset balances
- Order status updates

## Database Schema

### users
- `id` (primary key)
- `balance` (decimal, USD funds)
- Standard Laravel user columns

### assets
- `id` (primary key)
- `user_id` (foreign key)
- `symbol` (string, e.g., BTC, ETH)
- `amount` (decimal, total asset balance)
- `locked_amount` (decimal, reserved for open sell orders)
- Unique constraint on `(user_id, symbol)`

### orders
- `id` (primary key)
- `user_id` (foreign key)
- `symbol` (string)
- `side` (enum: 'buy', 'sell')
- `price` (decimal)
- `amount` (decimal)
- `status` (tinyint: 1=open, 2=filled, 3=cancelled)
- Indexed on `(symbol, side, status)` for efficient matching

### trades
- `id` (primary key)
- `buy_order_id` (foreign key)
- `sell_order_id` (foreign key)
- `buyer_id` (foreign key)
- `seller_id` (foreign key)
- `symbol` (string)
- `price` (decimal, execution price)
- `amount` (decimal)
- `commission` (decimal, 1.5% of trade value)

## Business Logic

### Order Placement

**Buy Orders:**
1. Check if `users.balance >= amount * price`
2. Deduct `amount * price` from `users.balance`
3. Mark order as open

**Sell Orders:**
1. Check if `assets.amount >= amount` (considering locked_amount)
2. Move `amount` into `assets.locked_amount`
3. Mark order as open

### Order Matching

- **New BUY** → matches with first SELL where `sell.price <= buy.price` and `sell.amount == buy.amount`
- **New SELL** → matches with first BUY where `buy.price >= sell.price` and `buy.amount == sell.amount`
- Only exact amount matches are supported (no partial fills)

### Commission

Commission is calculated as **1.5%** of the matched USD value:
- Example: 0.01 BTC @ 95,000 USD = 950 USD volume
- Fee = 950 * 0.015 = 14.25 USD
- Commission is deducted from the seller's receipt (consistent implementation)

### Race Safety

All balance and asset operations use database transactions with row-level locking (`lockForUpdate()`) to prevent race conditions.

## Testing

Run the test suite:

```bash
php artisan test
```

## Security

- All API endpoints require authentication
- CSRF protection enabled
- Input validation via Form Requests
- Database transactions ensure atomicity
- Row-level locking prevents race conditions

## License

MIT

