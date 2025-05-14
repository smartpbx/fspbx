<template>
    <form @submit.prevent="$emit('submit', formData)" class="space-y-6">
        <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-6">
            <div class="sm:col-span-6">
                <div class="relative flex items-start">
                    <div class="flex h-6 items-center">
                        <input id="enabled" name="enabled" type="checkbox" v-model="formData.enabled"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                        <label for="enabled" class="font-medium text-gray-900">Enabled</label>
                        <p class="text-gray-500">Enable or disable selected extensions</p>
                    </div>
                </div>
            </div>

            <div class="sm:col-span-6">
                <div class="relative flex items-start">
                    <div class="flex h-6 items-center">
                        <input id="update_password" name="update_password" type="checkbox" v-model="formData.update_password"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                        <label for="update_password" class="font-medium text-gray-900">Update Password</label>
                        <p class="text-gray-500">Set a new password for all selected extensions</p>
                    </div>
                </div>
            </div>

            <div v-if="formData.update_password" class="sm:col-span-6">
                <label for="password" class="block text-sm font-medium leading-6 text-gray-900">New Password</label>
                <div class="mt-2">
                    <input type="password" name="password" id="password" v-model="formData.password"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        :class="{ 'ring-red-300 focus:ring-red-500': errors.password }" />
                    <p v-if="errors.password" class="mt-2 text-sm text-red-600">{{ errors.password[0] }}</p>
                </div>
            </div>

            <div class="sm:col-span-6">
                <div class="relative flex items-start">
                    <div class="flex h-6 items-center">
                        <input id="update_type" name="update_type" type="checkbox" v-model="formData.update_type"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                        <label for="update_type" class="font-medium text-gray-900">Update Type</label>
                        <p class="text-gray-500">Change the type for all selected extensions</p>
                    </div>
                </div>
            </div>

            <div v-if="formData.update_type" class="sm:col-span-6">
                <label for="type" class="block text-sm font-medium leading-6 text-gray-900">New Type</label>
                <div class="mt-2">
                    <select id="type" name="type" v-model="formData.type"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        :class="{ 'ring-red-300 focus:ring-red-500': errors.type }">
                        <option v-for="type in options.types" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                    <p v-if="errors.type" class="mt-2 text-sm text-red-600">{{ errors.type[0] }}</p>
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-x-6">
            <button type="button" @click="$emit('cancel')"
                class="text-sm font-semibold leading-6 text-gray-900">Cancel</button>
            <button type="submit" :disabled="isSubmitting || !hasChanges"
                class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed">
                <span v-if="isSubmitting">Updating...</span>
                <span v-else>Update Selected</span>
            </button>
        </div>
    </form>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        required: true
    },
    options: {
        type: Object,
        required: true
    },
    errors: {
        type: Object,
        default: () => ({})
    },
    isSubmitting: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['submit', 'cancel']);

const formData = ref({
    enabled: null,
    update_password: false,
    password: '',
    update_type: false,
    type: ''
});

const hasChanges = computed(() => {
    return formData.value.enabled !== null ||
        (formData.value.update_password && formData.value.password) ||
        (formData.value.update_type && formData.value.type);
});

// Reset form when items change
watch(() => props.items, () => {
    formData.value = {
        enabled: null,
        update_password: false,
        password: '',
        update_type: false,
        type: ''
    };
}, { immediate: true });
</script> 