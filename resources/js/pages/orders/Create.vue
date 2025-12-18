<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { BreadcrumbItem } from '@/types';
import { useOrders } from '@/composables/useOrders';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Trading Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Place Order',
        href: '/orders/create',
    },
];

const symbols = ['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'ADA', 'XRP', 'DOT', 'DOGE', 'MATIC'];

const form = ref({
    symbol: 'BTC',
    side: 'buy',
    price: '',
    amount: '',
});

const { submitOrder, processing, errors, profile, isLoadingProfile } = useOrders();

const totalValue = computed(() => {
    const price = parseFloat(form.value.price) || 0;
    const amount = parseFloat(form.value.amount) || 0;
    return price * amount;
});

const formatNumber = (num: number, decimals: number = 2): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(num);
};

const onSubmit = async () => {
    const success = await submitOrder(form.value);
    if (success) {
        router.visit('/dashboard');
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Place Order" />

        <div class="min-h-screen bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950 p-4 lg:p-6">
            <div class="mx-auto max-w-5xl">
                <!-- Header -->
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-white">Place Order</h1>
                        <p class="text-zinc-400">Create a new limit order</p>
                    </div>
                    <Link 
                        href="/dashboard" 
                        class="flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm text-zinc-300 transition-colors hover:bg-zinc-700"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Dashboard
                    </Link>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Order Form -->
                    <div class="lg:col-span-2">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 backdrop-blur">
                            <form @submit.prevent="onSubmit">
                                <!-- Buy/Sell Toggle -->
                                <div class="grid grid-cols-2 border-b border-zinc-800">
                                    <button
                                        type="button"
                                        @click="form.side = 'buy'"
                                        :class="[
                                            'flex items-center justify-center gap-2 py-4 text-lg font-bold transition-all',
                                            form.side === 'buy'
                                                ? 'bg-emerald-500/20 text-emerald-400 border-b-2 border-emerald-500'
                                                : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800/50'
                                        ]"
                                    >
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                                        </svg>
                                        BUY
                                    </button>
                                    <button
                                        type="button"
                                        @click="form.side = 'sell'"
                                        :class="[
                                            'flex items-center justify-center gap-2 py-4 text-lg font-bold transition-all',
                                            form.side === 'sell'
                                                ? 'bg-red-500/20 text-red-400 border-b-2 border-red-500'
                                                : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800/50'
                                        ]"
                                    >
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                                        </svg>
                                        SELL
                                    </button>
                                </div>

                                <div class="space-y-6 p-6">
                                    <!-- Symbol Selection -->
                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-zinc-400">Select Asset</label>
                                        <div class="grid grid-cols-5 gap-2">
                                            <button
                                                v-for="symbol in symbols"
                                                :key="symbol"
                                                type="button"
                                                @click="form.symbol = symbol"
                                                :class="[
                                                    'rounded-lg border py-3 text-sm font-semibold transition-all',
                                                    form.symbol === symbol
                                                        ? 'border-emerald-500 bg-emerald-500/20 text-emerald-400'
                                                        : 'border-zinc-700 bg-zinc-800/50 text-zinc-400 hover:border-zinc-600 hover:text-white'
                                                ]"
                                            >
                                                {{ symbol }}
                                            </button>
                                        </div>
                                        <p v-if="errors.symbol" class="mt-2 text-sm text-red-400">{{ errors.symbol }}</p>
                                    </div>

                                    <!-- Price Input -->
                                    <div>
                                        <label for="price" class="mb-2 block text-sm font-medium text-zinc-400">
                                            Price (USD)
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-500">$</span>
                                            <input
                                                id="price"
                                                v-model="form.price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                required
                                                placeholder="0.00"
                                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 py-4 pl-8 pr-4 font-mono text-lg text-white placeholder-zinc-600 outline-none transition-colors focus:border-emerald-500"
                                                :class="{ 'border-red-500': errors.price }"
                                            />
                                        </div>
                                        <p v-if="errors.price" class="mt-2 text-sm text-red-400">{{ errors.price }}</p>
                                    </div>

                                    <!-- Amount Input -->
                                    <div>
                                        <label for="amount" class="mb-2 block text-sm font-medium text-zinc-400">
                                            Amount ({{ form.symbol }})
                                        </label>
                                        <div class="relative">
                                            <input
                                                id="amount"
                                                v-model="form.amount"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                required
                                                placeholder="0.0000"
                                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 py-4 px-4 font-mono text-lg text-white placeholder-zinc-600 outline-none transition-colors focus:border-emerald-500"
                                                :class="{ 'border-red-500': errors.amount }"
                                            />
                                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-500">{{ form.symbol }}</span>
                                        </div>
                                        <p v-if="errors.amount" class="mt-2 text-sm text-red-400">{{ errors.amount }}</p>
                                    </div>

                                    <!-- Quick Amount Buttons -->
                                    <div class="flex gap-2">
                                        <button
                                            v-for="pct in [25, 50, 75, 100]"
                                            :key="pct"
                                            type="button"
                                            class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/50 py-2 text-sm font-medium text-zinc-400 transition-colors hover:border-zinc-600 hover:text-white"
                                        >
                                            {{ pct }}%
                                        </button>
                                    </div>

                                    <!-- Error Message -->
                                    <div v-if="errors.general" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4">
                                        <p class="text-sm text-red-400">{{ errors.general }}</p>
                                    </div>

                                    <!-- Submit Button -->
                                    <Button
                                        type="submit"
                                        :disabled="processing || !form.price || !form.amount"
                                        :class="[
                                            'w-full py-6 text-lg font-bold transition-all',
                                            form.side === 'buy'
                                                ? 'bg-emerald-600 hover:bg-emerald-500 disabled:bg-emerald-600/50'
                                                : 'bg-red-600 text-white hover:bg-red-500 disabled:bg-red-600/50'
                                        ]"
                                    >
                                        <span v-if="processing" class="flex items-center justify-center gap-2">
                                            <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Processing...
                                        </span>
                                        <span v-else>
                                            {{ form.side === 'buy' ? 'Buy' : 'Sell' }} {{ form.symbol }}
                                        </span>
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Order Summary Sidebar -->
                    <div class="space-y-4">
                        <!-- Order Preview -->
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 backdrop-blur">
                            <h3 class="mb-4 text-sm font-medium text-zinc-400">Order Summary</h3>
                            
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500">Type</span>
                                    <span class="font-medium text-white">Limit Order</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500">Side</span>
                                    <span :class="form.side === 'buy' ? 'text-emerald-400' : 'text-red-400'" class="font-bold uppercase">
                                        {{ form.side }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500">Asset</span>
                                    <span class="font-medium text-white">{{ form.symbol }}/USD</span>
                                </div>
                                
                                <div class="my-3 border-t border-zinc-800"></div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500">Price</span>
                                    <span class="font-mono text-white">
                                        {{ form.price ? `$${formatNumber(parseFloat(form.price))}` : '—' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500">Amount</span>
                                    <span class="font-mono text-white">
                                        {{ form.amount ? `${formatNumber(parseFloat(form.amount), 4)} ${form.symbol}` : '—' }}
                                    </span>
                                </div>
                                
                                <div class="my-3 border-t border-zinc-800"></div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-400">Total Value</span>
                                    <span class="text-xl font-bold text-white">
                                        ${{ formatNumber(totalValue) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Balance Info -->
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 backdrop-blur">
                            <h3 class="mb-4 text-sm font-medium text-zinc-400">Available Balance</h3>
                            
                            <div v-if="isLoadingProfile" class="space-y-2">
                                <div class="h-6 w-24 animate-pulse rounded bg-zinc-800"></div>
                            </div>
                            <div v-else-if="profile" class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500">USD</span>
                                    <span class="font-mono font-medium text-white">${{ formatNumber(profile.balance) }}</span>
                                </div>
                                <div v-for="asset in profile.assets" :key="asset.symbol" class="flex items-center justify-between">
                                    <span class="text-zinc-500">{{ asset.symbol }}</span>
                                    <span class="font-mono font-medium text-white">{{ formatNumber(asset.available, 4) }}</span>
                                </div>
                            </div>
                            <div v-else class="text-zinc-600">No balance data</div>
                        </div>

                        <!-- Info Box -->
                        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                            <div class="flex gap-3">
                                <svg class="h-5 w-5 flex-shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="text-sm text-amber-200/80">
                                    <p class="font-medium text-amber-400">Limit Orders</p>
                                    <p class="mt-1">Your order will be placed in the orderbook and executed when a matching order is found at your specified price.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
