// Two-factor authentication is disabled
// This composable is stubbed out to prevent errors in components that reference it
import { computed, ref } from 'vue';

const errors = ref<string[]>([]);
const manualSetupKey = ref<string | null>(null);
const qrCodeSvg = ref<string | null>(null);
const recoveryCodesList = ref<string[]>([]);

const hasSetupData = computed<boolean>(
    () => false, // Always false since 2FA is disabled
);

export const useTwoFactorAuth = () => {
    const fetchQrCode = async (): Promise<void> => {
        // No-op: Two-factor authentication is disabled
    };

    const fetchSetupKey = async (): Promise<void> => {
        // No-op: Two-factor authentication is disabled
    };

    const clearSetupData = (): void => {
        manualSetupKey.value = null;
        qrCodeSvg.value = null;
        clearErrors();
    };

    const clearErrors = (): void => {
        errors.value = [];
    };

    const clearTwoFactorAuthData = (): void => {
        clearSetupData();
        clearErrors();
        recoveryCodesList.value = [];
    };

    const fetchRecoveryCodes = async (): Promise<void> => {
        // No-op: Two-factor authentication is disabled
    };

    const fetchSetupData = async (): Promise<void> => {
        // No-op: Two-factor authentication is disabled
    };

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        errors,
        hasSetupData,
        clearSetupData,
        clearErrors,
        clearTwoFactorAuthData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
