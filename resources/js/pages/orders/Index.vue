<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { watch, computed, ref } from 'vue';
import type { BreadcrumbItem } from '@/types';
import { useOrders } from '@/composables/useOrders';

interface Order {
    id: number;
    symbol: string;
    side: string;
    price: string;
    amount: string;
    status: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    orders: {
        data: Order[];
        links: any;
        meta: any;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Trading Dashboard',
        href: '/dashboard',
    },
];

const { 
    orders, 
    selectedSymbol, 
    orderbook, 
    profile, 
    isLoadingProfile, 
    isLoadingOrderbook,
    cancelOrder,
    fetchProfile,
    fetchOrderbook,
} = useOrders(props.orders.data);

// Cancel confirmation modal state
const showCancelModal = ref(false);
const orderToCancel = ref<Order | null>(null);
const isCancelling = ref(false);

const openCancelModal = (order: Order) => {
    orderToCancel.value = order;
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    showCancelModal.value = false;
    orderToCancel.value = null;
};

const confirmCancelOrder = async () => {
    if (!orderToCancel.value) return;
    
    isCancelling.value = true;
    const success = await cancelOrder(orderToCancel.value.id);
    
    if (success) {
        const order = orders.value.find(o => o.id === orderToCancel.value?.id);
        if (order) {
            order.status = 3;
        }
        fetchProfile();
    }
    
    isCancelling.value = false;
    closeCancelModal();
};

watch(() => props.orders.data, (newVal) => {
    orders.value = newVal;
});

// Always sort orders by created_at descending (newest first)
const sortedOrders = computed(() => {
    return [...orders.value].sort((a, b) => 
        new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    );
});

const symbols = ['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'ADA', 'XRP', 'DOT', 'DOGE', 'MATIC'];

const statusConfig: Record<number, { label: string; class: string }> = {
    1: { label: 'OPEN', class: 'bg-amber-500/20 text-amber-400 border border-amber-500/30' },
    2: { label: 'FILLED', class: 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' },
    3: { label: 'CANCELLED', class: 'bg-zinc-500/20 text-zinc-400 border border-zinc-500/30' },
};

const formatNumber = (num: number, decimals: number = 2): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(num);
};

const formatPrice = (price: string | number): string => formatNumber(Number(price), 2);
const formatAmount = (amount: string | number): string => formatNumber(Number(amount), 4);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Trading Dashboard" />

        <div class="min-h-screen bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950 p-4 lg:p-6">
            <!-- Header Stats -->
            <div class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- USD Balance -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-400">USD Balance</span>
                        <div class="rounded-full bg-emerald-500/10 p-2">
                            <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div v-if="isLoadingProfile" class="mt-2 h-8 w-24 animate-pulse rounded bg-zinc-800"></div>
                    <div v-else-if="profile" class="mt-2 text-2xl font-bold text-white">
                        ${{ formatNumber(profile.balance) }}
                    </div>
                    <div v-else class="mt-2 text-2xl font-bold text-zinc-600">$0.00</div>
                </div>

                <!-- Assets Count -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-400">Total Assets</span>
                        <div class="rounded-full bg-blue-500/10 p-2">
                            <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                    </div>
                    <div v-if="isLoadingProfile" class="mt-2 h-8 w-16 animate-pulse rounded bg-zinc-800"></div>
                    <div v-else-if="profile" class="mt-2 text-2xl font-bold text-white">
                        {{ profile.assets.length }}
                    </div>
                    <div v-else class="mt-2 text-2xl font-bold text-zinc-600">0</div>
                </div>

                <!-- Open Orders -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-400">Open Orders</span>
                        <div class="rounded-full bg-amber-500/10 p-2">
                            <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-white">
                        {{ sortedOrders.filter(o => o.status === 1).length }}
                    </div>
                </div>

                <!-- Quick Trade -->
                <Link href="/orders/create" class="group rounded-xl border border-emerald-500/30 bg-gradient-to-br from-emerald-500/20 to-emerald-600/10 p-5 transition-all hover:border-emerald-500/50 hover:from-emerald-500/30">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-emerald-400">New Order</span>
                        <div class="rounded-full bg-emerald-500/20 p-2 transition-transform group-hover:scale-110">
                            <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-lg font-bold text-white">Place Order →</div>
                </Link>
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Orderbook -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 backdrop-blur lg:col-span-1">
                    <div class="flex items-center justify-between border-b border-zinc-800 p-4">
                        <h2 class="text-lg font-bold text-white">Order Book</h2>
                        <select
                            v-model="selectedSymbol"
                            class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white outline-none focus:border-emerald-500"
                        >
                            <option v-for="symbol in symbols" :key="symbol" :value="symbol">
                                {{ symbol }}/USD
                            </option>
                        </select>
                    </div>
                    
                    <div class="p-4">
                        <!-- Header -->
                        <div class="mb-2 grid grid-cols-3 text-xs font-medium text-zinc-500">
                            <span>Price (USD)</span>
                            <span class="text-center">Amount</span>
                            <span class="text-right">Total</span>
                        </div>

                        <!-- Asks (Sells) - Reversed for display -->
                        <div class="mb-4 space-y-0.5">
                            <div v-if="isLoadingOrderbook" class="space-y-1">
                                <div v-for="i in 5" :key="i" class="h-6 animate-pulse rounded bg-zinc-800"></div>
                            </div>
                            <template v-else-if="orderbook && orderbook.asks.length > 0">
                                <div
                                    v-for="(ask, index) in [...orderbook.asks].reverse().slice(0, 8)"
                                    :key="`ask-${index}`"
                                    class="group relative grid grid-cols-3 rounded py-1 text-sm"
                                >
                                    <div class="absolute inset-0 rounded bg-red-500/10" :style="{ width: `${Math.min((ask.amount / 10) * 100, 100)}%` }"></div>
                                    <span class="relative font-mono font-medium text-red-400">{{ formatPrice(ask.price) }}</span>
                                    <span class="relative text-center font-mono text-zinc-300">{{ formatAmount(ask.amount) }}</span>
                                    <span class="relative text-right font-mono text-zinc-500">{{ formatNumber(Number(ask.price) * ask.amount, 2) }}</span>
                                </div>
                            </template>
                            <div v-else class="py-4 text-center text-sm text-zinc-600">No sell orders</div>
                        </div>

                        <!-- Spread -->
                        <div class="my-3 flex items-center justify-center gap-2 border-y border-zinc-800 py-2">
                            <span class="text-xs text-zinc-500">Spread</span>
                            <span class="font-mono text-sm font-bold text-white">
                                {{ orderbook && orderbook.asks.length > 0 && orderbook.bids.length > 0 
                                    ? formatPrice(Number(orderbook.asks[0]?.price || 0) - Number(orderbook.bids[0]?.price || 0))
                                    : '—' }}
                            </span>
                        </div>

                        <!-- Bids (Buys) -->
                        <div class="space-y-0.5">
                            <div v-if="isLoadingOrderbook" class="space-y-1">
                                <div v-for="i in 5" :key="i" class="h-6 animate-pulse rounded bg-zinc-800"></div>
                            </div>
                            <template v-else-if="orderbook && orderbook.bids.length > 0">
                                <div
                                    v-for="(bid, index) in orderbook.bids.slice(0, 8)"
                                    :key="`bid-${index}`"
                                    class="group relative grid grid-cols-3 rounded py-1 text-sm"
                                >
                                    <div class="absolute inset-0 rounded bg-emerald-500/10" :style="{ width: `${Math.min((bid.amount / 10) * 100, 100)}%` }"></div>
                                    <span class="relative font-mono font-medium text-emerald-400">{{ formatPrice(bid.price) }}</span>
                                    <span class="relative text-center font-mono text-zinc-300">{{ formatAmount(bid.amount) }}</span>
                                    <span class="relative text-right font-mono text-zinc-500">{{ formatNumber(Number(bid.price) * bid.amount, 2) }}</span>
                                </div>
                            </template>
                            <div v-else class="py-4 text-center text-sm text-zinc-600">No buy orders</div>
                        </div>
                    </div>
                </div>

                <!-- Orders & Assets -->
                <div class="space-y-4 lg:col-span-2">
                    <!-- Assets -->
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 backdrop-blur">
                        <div class="border-b border-zinc-800 p-4">
                            <h2 class="text-lg font-bold text-white">Your Assets</h2>
                        </div>
                        <div class="p-4">
                            <div v-if="isLoadingProfile" class="space-y-2">
                                <div v-for="i in 3" :key="i" class="h-12 animate-pulse rounded bg-zinc-800"></div>
                            </div>
                            <div v-else-if="profile && profile.assets.length > 0" class="space-y-2">
                                <div
                                    v-for="asset in profile.assets"
                                    :key="asset.symbol"
                                    class="flex items-center justify-between rounded-lg bg-zinc-800/50 p-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-orange-600 font-bold text-white">
                                            {{ asset.symbol.charAt(0) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-white">{{ asset.symbol }}</div>
                                            <div class="text-xs text-zinc-500">Available: {{ formatAmount(asset.available) }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono text-lg font-bold text-white">{{ formatAmount(asset.amount) }}</div>
                                        <div v-if="asset.locked_amount > 0" class="text-xs text-amber-400">
                                            🔒 {{ formatAmount(asset.locked_amount) }} locked
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center text-zinc-500">
                                <svg class="mx-auto h-12 w-12 text-zinc-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <p class="mt-2">No assets yet</p>
                            </div>
                        </div>
                    </div>

                    <!-- Orders -->
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 backdrop-blur">
                        <div class="flex items-center justify-between border-b border-zinc-800 p-4">
                            <h2 class="text-lg font-bold text-white">Order History</h2>
                            <Button as-child size="sm" class="bg-emerald-600 hover:bg-emerald-500">
                                <Link href="/orders/create">+ New Order</Link>
                            </Button>
                        </div>
                        <div class="divide-y divide-zinc-800">
                            <div v-if="sortedOrders.length === 0" class="py-12 text-center text-zinc-500">
                                <svg class="mx-auto h-12 w-12 text-zinc-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p class="mt-2">No orders yet</p>
                                <Link href="/orders/create" class="mt-3 inline-block text-emerald-400 hover:text-emerald-300">
                                    Place your first order →
                                </Link>
                            </div>
                            <div
                                v-for="order in sortedOrders"
                                :key="order.id"
                                class="flex items-center justify-between p-4 transition-colors hover:bg-zinc-800/30"
                            >
                                <div class="flex items-center gap-4">
                                    <div
                                        :class="[
                                            'flex h-10 w-10 items-center justify-center rounded-lg font-bold',
                                            order.side === 'buy' 
                                                ? 'bg-emerald-500/20 text-emerald-400' 
                                                : 'bg-red-500/20 text-red-400'
                                        ]"
                                    >
                                        {{ order.side === 'buy' ? '↑' : '↓' }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-white">{{ order.symbol }}</span>
                                            <span
                                                :class="[
                                                    'text-xs font-bold uppercase',
                                                    order.side === 'buy' ? 'text-emerald-400' : 'text-red-400'
                                                ]"
                                            >
                                                {{ order.side }}
                                            </span>
                                            <span :class="['rounded px-2 py-0.5 text-xs font-medium', statusConfig[order.status].class]">
                                                {{ statusConfig[order.status].label }}
                                            </span>
                                        </div>
                                        <div class="mt-0.5 text-sm text-zinc-400">
                                            {{ formatAmount(order.amount) }} @ ${{ formatPrice(order.price) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="font-mono text-sm font-medium text-white">
                                            ${{ formatNumber(Number(order.price) * Number(order.amount), 2) }}
                                        </div>
                                        <div class="text-xs text-zinc-500">
                                            {{ new Date(order.created_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                    <Button
                                        v-if="order.status === 1"
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-400 hover:bg-red-500/20 hover:text-red-300"
                                        @click="openCancelModal(order)"
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Confirmation Modal -->
        <Teleport to="body">
            <div 
                v-if="showCancelModal" 
                class="fixed inset-0 z-50 flex items-center justify-center"
            >
                <!-- Backdrop -->
                <div 
                    class="absolute inset-0 bg-black/70 backdrop-blur-sm" 
                    @click="closeCancelModal"
                ></div>
                
                <!-- Modal -->
                <div class="relative w-full max-w-md rounded-xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl">
                    <!-- Icon -->
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-500/20">
                        <svg class="h-7 w-7 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    
                    <!-- Content -->
                    <h3 class="mb-2 text-center text-xl font-bold text-white">Cancel Order</h3>
                    <p class="mb-6 text-center text-zinc-400">
                        Are you sure you want to cancel this order?
                    </p>
                    
                    <!-- Order Details -->
                    <div v-if="orderToCancel" class="mb-6 rounded-lg bg-zinc-800/50 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div 
                                    :class="[
                                        'flex h-10 w-10 items-center justify-center rounded-lg font-bold',
                                        orderToCancel.side === 'buy' 
                                            ? 'bg-emerald-500/20 text-emerald-400' 
                                            : 'bg-red-500/20 text-red-400'
                                    ]"
                                >
                                    {{ orderToCancel.side === 'buy' ? '↑' : '↓' }}
                                </div>
                                <div>
                                    <div class="font-semibold text-white">{{ orderToCancel.symbol }}</div>
                                    <div :class="orderToCancel.side === 'buy' ? 'text-emerald-400' : 'text-red-400'" class="text-sm font-medium uppercase">
                                        {{ orderToCancel.side }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono text-white">{{ formatNumber(Number(orderToCancel.amount), 4) }} {{ orderToCancel.symbol }}</div>
                                <div class="text-sm text-zinc-500">@ ${{ formatPrice(orderToCancel.price) }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex gap-3">
                        <Button 
                            variant="outline" 
                            class="flex-1 border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700"
                            @click="closeCancelModal"
                            :disabled="isCancelling"
                        >
                            Keep Order
                        </Button>
                        <Button 
                            class="flex-1 bg-red-600 text-white hover:bg-red-500"
                            @click="confirmCancelOrder"
                            :disabled="isCancelling"
                        >
                            <span v-if="isCancelling" class="flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Cancelling...
                            </span>
                            <span v-else>Yes, Cancel Order</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
