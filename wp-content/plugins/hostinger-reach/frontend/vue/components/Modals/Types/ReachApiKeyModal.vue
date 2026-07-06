<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { useModal } from '@/composables/useModal';
import { useToast } from '@/composables/useToast';
import { reachRepo } from '@/data/repositories/reachRepo';
import { Route } from '@/types/enums/routeEnum';
import { translate } from '@/utils/translate';

interface Props {
	apiKey?: string;
	csrf?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
	apiKey: '',
	csrf: null
});

const { closeModal } = useModal();
const { showError } = useToast();
const router = useRouter();

const isLoading = ref(false);
const key = ref(props.apiKey || '');

const getCsrfFromGenerateAuthUrl = async (): Promise<string | null> => {
	const [data] = await reachRepo.generateAuthUrl();
	if (!data?.authUrl) return null;

	try {
		const url = new URL(data.authUrl);
		const redirectUrl = url.searchParams.get('redirectUrl');
		if (!redirectUrl) return null;

		const decoded = decodeURIComponent(redirectUrl);
		const innerUrl = new URL(decoded);
		const token = innerUrl.searchParams.get('token');

		return token;
	} catch (error) {
		console.error(error);

		return null;
	}
};

const tryConnect = async (csrf: string, token: string) => {
	isLoading.value = true;

	const [data, error] = await reachRepo.postToken(csrf, token);

	isLoading.value = false;

	if (error || !data?.success) {
		showError(error?.message || translate('hostinger_reach_error_message'));

		return;
	}

	hostinger_reach_reach_data.is_connected = true;
	closeModal();
	router.push({ name: Route.Base.OVERVIEW });
};

const handleSaveAndConnect = async () => {
	if (!key.value) {
		showError(translate('hostinger_reach_error_message'));

		return;
	}

	let csrf = props.csrf;
	if (!csrf) {
		csrf = await getCsrfFromGenerateAuthUrl();
	}

	if (!csrf) {
		showError(translate('hostinger_reach_error_message'));

		return;
	}

	await tryConnect(csrf, key.value);
};

onMounted(() => {
	if (props.apiKey && props.csrf) {
		tryConnect(props.csrf, props.apiKey);
	}
});
</script>

<template>
	<div class="reach-api-key-modal">
		<HText as="h3" variant="heading-3" class="h-mb-12">
			{{ translate('hostinger_reach_reach_api_key_modal_title') }}
		</HText>
		<input
			id="reach-api-key-modal-input"
			class="reach-api-key-modal__input"
			type="password"
			:value="key"
			autocomplete="off"
			@input="key = ($event.target as HTMLInputElement).value"
		/>
		<div class="reach-api-key-modal__info">
			<HSnackbar
				variant="info"
				:description="translate('hostinger_reach_reach_api_key_modal_notice')"
				:show-close-icon="false"
				:hide-icon="false"
			/>
		</div>

		<div class="reach-api-key-modal__actions">
			<HButton variant="text" color="primary" size="small" @click="closeModal">
				{{ translate('hostinger_reach_reach_api_key_modal_cancel') }}
			</HButton>
			<HButton color="primary" size="small" :is-loading="isLoading" @click="handleSaveAndConnect">
				{{ translate('hostinger_reach_reach_api_key_modal_button') }}
			</HButton>
		</div>
	</div>
</template>

<style scoped lang="scss">
.reach-api-key-modal {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-width: 520px;

	&__label {
		color: var(--neutral--700);
		font-weight: 600;
	}

	&__input {
		width: 100%;
		border: 1px solid var(--neutral--200);
		border-radius: 8px;
		padding: 8px 12px;
		font-size: 14px;
	}

	&__info {
		margin-top: 8px;
	}

	&__actions {
		display: flex;
		gap: 12px;
		align-items: center;
		margin-top: 8px;
		justify-content: flex-end;
	}
}
</style>
