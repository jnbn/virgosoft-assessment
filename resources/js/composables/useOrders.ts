import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

// Helper to get cookie value (decodes URL-encoded values like XSRF-TOKEN)
function getCookie(name: string): string | null {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        const cookieValue = parts.pop()?.split(';').shift() || null;
        return cookieValue ? decodeURIComponent(cookieValue) : null;
    }
    return null;
}

export interface Order {
    id: number;
    symbol: string;
    side: 'buy' | 'sell';
    price: string;
    amount: string;
    status: number;
    created_at: string;
    updated_at: string;
    user_id: number;
}

export interface OrderForm {
    symbol: string;
    side: string;
    price: string;
    amount: string;
}

export interface OrderbookEntry {
    price: string; // Price is string from API but we might convert for sorting
    amount: number;
    count: number;
}

export interface Orderbook {
    symbol: string;
    bids: OrderbookEntry[];
    asks: OrderbookEntry[];
}

export interface Asset {
    symbol: string;
    amount: number;
    locked_amount: number;
    available: number;
}

export interface Profile {
    balance: number;
    assets: Asset[];
}

export function useOrders(initialOrders: Order[] = []) {
    const orders = ref<Order[]>(initialOrders);
    const selectedSymbol = ref<string>('BTC');
    const orderbook = ref<Orderbook>({ symbol: 'BTC', bids: [], asks: [] });
    const profile = ref<Profile | null>(null);
    const isLoadingProfile = ref(false);
    const isLoadingOrderbook = ref(false);
    const processing = ref(false);
    const errors = ref<Record<string, string>>({});

    const page = usePage();
    const currentUserId = computed(() => (page.props.auth as any)?.user?.id);

    const fetchProfile = async () => {
        try {
            isLoadingProfile.value = true;
            const response = await fetch('/api/profile', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.ok) {
                profile.value = await response.json();
            }
        } finally {
            isLoadingProfile.value = false;
        }
    };

    const fetchOrderbook = async () => {
        try {
            isLoadingOrderbook.value = true;
            const response = await fetch(`/api/orders?symbol=${selectedSymbol.value}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.ok) {
                const data = await response.json();
                processOrderbook(data.orders);
            }
        } finally {
            isLoadingOrderbook.value = false;
        }
    };

    const processOrderbook = (rawOrders: Order[]) => {
        const calculateEntries = (list: Order[]): OrderbookEntry[] => {
            return list.reduce((acc: OrderbookEntry[], order: Order) => {
                const existing = acc.find((b) => b.price === order.price); // String comparison fine if normalized
                const amount = parseFloat(order.amount);
                if (existing) {
                    existing.amount += amount;
                    existing.count += 1;
                } else {
                    acc.push({ price: order.price, amount: amount, count: 1 });
                }
                return acc;
            }, []);
        };

        const bids = calculateEntries(
            rawOrders.filter((o) => o.side === 'buy')
        )
            .sort((a, b) => parseFloat(b.price) - parseFloat(a.price))
            .slice(0, 10);
            
        const asks = calculateEntries(
            rawOrders.filter((o) => o.side === 'sell')
        )
            .sort((a, b) => parseFloat(a.price) - parseFloat(b.price))
            .slice(0, 10);
            
        orderbook.value = { symbol: selectedSymbol.value, bids, asks };
    };

    const submitOrder = async (form: OrderForm) => {
        processing.value = true;
        errors.value = {};
        
        try {
            const response = await fetch('/api/orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || '',
                },
                body: JSON.stringify(form),
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                if (data.errors) errors.value = data.errors;
                else errors.value = { general: data.message || 'Failed' };
                return false;
            }
            return true;
        } catch (e) {
            errors.value = { general: 'Network error' };
            return false;
        } finally {
            processing.value = false;
        }
    };

    const cancelOrder = async (orderId: number): Promise<boolean> => {
        try {
            const response = await fetch(`/api/orders/${orderId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || '',
                },
            });
            return response.ok;
        } catch (e) {
            console.error('Cancel failed', e);
            return false;
        }
    };
    
    // Real-time
    let orderbookChannel: string | null = null;
    
    const setupListeners = () => {
        if (!window.Echo) {
            console.warn('[Echo] Echo not initialized - check Pusher credentials');
            return;
        }
        if (!currentUserId.value) {
            console.warn('[Echo] No current user ID');
            return;
        }
        
        console.log('[Echo] Setting up listeners for user:', currentUserId.value);
        
        // Private User Channel
        window.Echo.private(`private-user.${currentUserId.value}`)
            .listen('.order.matched', (data: any) => {
                console.log('[Echo] Order matched event received:', data);
                
                // Update profile directly from event data (no API call needed)
                if (data.buyer && data.buyer.id === currentUserId.value) {
                    profile.value = {
                        balance: data.buyer.balance,
                        assets: data.buyer.assets,
                    };
                } else if (data.seller && data.seller.id === currentUserId.value) {
                    profile.value = {
                        balance: data.seller.balance,
                        assets: data.seller.assets,
                    };
                }
                
                // Update order status in local orders array
                if (data.buy_order) {
                    const buyOrder = orders.value.find(o => o.id === data.buy_order.id);
                    if (buyOrder) {
                        buyOrder.status = data.buy_order.status;
                        buyOrder.amount = data.buy_order.amount;
                    }
                }
                if (data.sell_order) {
                    const sellOrder = orders.value.find(o => o.id === data.sell_order.id);
                    if (sellOrder) {
                        sellOrder.status = data.sell_order.status;
                        sellOrder.amount = data.sell_order.amount;
                    }
                }
                
                // Orderbook is updated via the public orderbook channel listener (no API call needed)
            })
            .error((error: any) => {
                console.error('[Echo] Private channel error:', error);
            });
            
        setupOrderbookListener(selectedSymbol.value);
    };
    
    // Helper to compare prices (handles both string and number formats)
    const pricesEqual = (a: string | number, b: string | number): boolean => {
        return parseFloat(String(a)) === parseFloat(String(b));
    };

    // Helper to add an order to the orderbook locally
    const addToOrderbook = (order: { side: string; price: number; amount: number }) => {
        const targetList = order.side === 'buy' ? orderbook.value.bids : orderbook.value.asks;
        
        const existing = targetList.find(e => pricesEqual(e.price, order.price));
        if (existing) {
            existing.amount += order.amount;
            existing.count += 1;
        } else {
            targetList.push({ price: String(order.price), amount: order.amount, count: 1 });
        }
        
        // Re-sort: bids descending, asks ascending
        if (order.side === 'buy') {
            orderbook.value.bids = [...orderbook.value.bids]
                .sort((a, b) => parseFloat(String(b.price)) - parseFloat(String(a.price)))
                .slice(0, 10);
        } else {
            orderbook.value.asks = [...orderbook.value.asks]
                .sort((a, b) => parseFloat(String(a.price)) - parseFloat(String(b.price)))
                .slice(0, 10);
        }
    };

    // Helper to remove an order from the orderbook locally
    const removeFromOrderbook = (order: { side: string; price: number; amount: number }) => {
        const targetList = order.side === 'buy' ? orderbook.value.bids : orderbook.value.asks;
        
        const existingIdx = targetList.findIndex(e => pricesEqual(e.price, order.price));
        if (existingIdx > -1) {
            const existing = targetList[existingIdx];
            existing.amount -= order.amount;
            existing.count -= 1;
            
            // Remove entry if empty
            if (existing.amount <= 0.00000001 || existing.count <= 0) {
                targetList.splice(existingIdx, 1);
            }
        }
    };

    const setupOrderbookListener = (symbol: string) => {
        if (!window.Echo) return;
        if (orderbookChannel) window.Echo.leave(`orderbook.${orderbookChannel}`);
        
        orderbookChannel = symbol;
        window.Echo.channel(`orderbook.${symbol}`)
            .listen('.order.placed', (data: any) => {
                console.log('[Echo] New order placed:', data);
                if (data.order) {
                    addToOrderbook({
                        side: data.order.side,
                        price: data.order.price,
                        amount: data.order.amount,
                    });
                }
            })
            .listen('.order.cancelled', (data: any) => {
                console.log('[Echo] Order cancelled:', data);
                if (data.order) {
                    removeFromOrderbook({
                        side: data.order.side,
                        price: data.order.price,
                        amount: data.order.amount || 0,
                    });
                }
            })
            .listen('.order.matched', (data: any) => {
                console.log('[Echo] Order matched:', data);
                // Remove both buy and sell orders from orderbook
                if (data.trade) {
                    removeFromOrderbook({ side: 'buy', price: data.trade.price, amount: data.trade.amount });
                    removeFromOrderbook({ side: 'sell', price: data.trade.price, amount: data.trade.amount });
                }
            });
    };

    watch(selectedSymbol, (newVal) => {
        fetchOrderbook();
        setupOrderbookListener(newVal);
    });

    onMounted(() => {
        fetchProfile();
        fetchOrderbook();
        if (window.Echo) setupListeners();
    });
    
    onUnmounted(() => {
        if (window.Echo && currentUserId.value) {
            window.Echo.leave(`private-user.${currentUserId.value}`);
        }
        if (window.Echo && orderbookChannel) {
            window.Echo.leave(`orderbook.${orderbookChannel}`);
        }
    });

    return {
        orders,
        selectedSymbol,
        orderbook,
        profile,
        isLoadingProfile,
        isLoadingOrderbook,
        processing,
        errors,
        fetchOrderbook,
        fetchProfile,
        submitOrder,
        cancelOrder
    };
}
