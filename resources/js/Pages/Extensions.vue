<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Extensions</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick"/>
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick"/>
                </div>
            </template>

            <template #action>
                <div class="flex items-center space-x-2">
                    <button type="button" v-if="page.props.auth.can.extensions_create" @click.prevent="handleCreateButtonClick()"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Create
                    </button>
                    <button type="button" v-if="page.props.auth.can.extensions_import" @click.prevent="handleImportButtonClick()"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Import
                    </button>
                    <button type="button" v-if="page.props.auth.can.extensions_destroy && selectedItems.length > 0" 
                        @click.prevent="handleBulkActionRequest('delete')"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-300 hover:bg-red-50">
                        Delete Selected
                    </button>
                </div>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>

            <template #table-header>
                <TableColumnHeader header=""
                    className="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">Extension</span>
                </TableColumnHeader>
                <TableColumnHeader header="Name" className="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Type" className="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Status" className="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Action" className="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="5">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
                        <button v-if="!selectAll && selectedItems.length !== data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.extension_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 flex">
                        <div class="flex items-center">
                            <input v-if="row.extension_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.extension_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9"
                                :class="{ 'cursor-pointer hover:text-gray-900': page.props.auth.can.extensions_update }"
                                @click="page.props.auth.can.extensions_update && handleEditRequest(row.extension_uuid)">
                                {{ row.extension }}
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.name" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.type" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <StatusBadge :enabled="row.enabled == 'true'" />
                    </TableField>

                    <TableField class="w-4 whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center space-x-2 whitespace-nowrap">
                                <ejs-tooltip v-if="page.props.auth.can.extensions_update" :content="'Edit extension'"
                                    position='TopLeft' target="#edit_tooltip_target">
                                    <div id="edit_tooltip_target">
                                        <PencilSquareIcon @click="handleEditRequest(row.extension_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                                <ejs-tooltip v-if="page.props.auth.can.extensions_destroy" :content="'Remove extension'"
                                    position='TopLeft' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.destroy_route)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Adjust your search and try again.
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
        </DataTable>
    </div>

    <AddEditItemModal :show="createModalTrigger" :header="'Add New Extension'" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <CreateExtensionForm :options="itemOptions" :errors="formErrors" :is-submitting="createFormSubmitting"
                @submit="handleCreateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="editModalTrigger" :header="'Update Extension - ' + itemOptions?.extension?.extension"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdateExtensionForm :options="itemOptions" :errors="formErrors" :is-submitting="updateFormSubmitting"
                @submit="handleUpdateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="bulkUpdateModalTrigger" :header="'Bulk Edit'" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <BulkUpdateExtensionForm :items="selectedItems" :options="itemOptions" :errors="formErrors"
                :is-submitting="updateFormSubmitting" @submit="handleBulkUpdateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import DataTable from '@/Pages/components/general/DataTable.vue';
import TableColumnHeader from '@/Pages/components/general/TableColumnHeader.vue';
import TableField from '@/Pages/components/general/TableField.vue';
import Paginator from '@/Pages/components/general/Paginator.vue';
import Loading from '@/Pages/components/general/Loading.vue';
import StatusBadge from '@/Pages/components/general/StatusBadge.vue';
import BulkActionButton from '@/Pages/components/general/BulkActionButton.vue';
import AddEditItemModal from '@/Pages/components/extensions/AddEditItemModal.vue';
import CreateExtensionForm from '@/Pages/components/extensions/CreateExtensionForm.vue';
import UpdateExtensionForm from '@/Pages/components/extensions/UpdateExtensionForm.vue';
import BulkUpdateExtensionForm from '@/Components/Forms/BulkUpdateExtensionForm.vue';
import { MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { Tooltip as EjsTooltip } from '@syncfusion/ej2-vue-popups';

const props = defineProps({
    data: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        required: true
    },
    auth: {
        type: Object,
        required: true
    }
});

const page = usePage();
const loading = ref(false);
const loadingModal = ref(false);
const createModalTrigger = ref(false);
const editModalTrigger = ref(false);
const bulkUpdateModalTrigger = ref(false);
const createFormSubmitting = ref(false);
const updateFormSubmitting = ref(false);
const selectPageItems = ref(false);
const selectAll = ref(false);
const selectedItems = ref([]);
const itemOptions = ref(null);
const formErrors = ref({});

const search = ref(props.filters.search || '');
const currentExtension = ref(null);
const showDeleteModal = ref(false);

// Watch for search changes and reload data
watch(search, (value) => {
    router.get(route('extensions.index'), { search: value }, {
        preserveState: true,
        preserveScroll: true,
        only: ['data']
    });
});

const bulkActions = [
    { name: 'Delete', value: 'delete' },
    { name: 'Enable', value: 'enable' },
    { name: 'Disable', value: 'disable' },
];

onMounted(() => {
    fetchData();
});

const fetchData = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('extensions.index'), {
            params: {
                page: data.value.current_page,
                search: search.value,
            },
        });
        data.value = response.data;
    } catch (error) {
        console.error('Error fetching extensions:', error);
    } finally {
        loading.value = false;
    }
};

const handleSearchButtonClick = () => {
    data.value.current_page = 1;
    fetchData();
};

const handleFiltersReset = () => {
    search.value = '';
    data.value.current_page = 1;
    fetchData();
};

const renderRequestedPage = (page) => {
    data.value.current_page = page;
    fetchData();
};

const handleCreateButtonClick = async () => {
    try {
        const response = await axios.get(route('extensions.create'));
        itemOptions.value = response.data;
        createModalTrigger.value = true;
    } catch (error) {
        console.error('Error fetching create options:', error);
    }
};

const handleEditRequest = async (uuid) => {
    loadingModal.value = true;
    try {
        const response = await axios.get(route('extensions.edit', { extension: uuid }));
        itemOptions.value = response.data;
        editModalTrigger.value = true;
    } catch (error) {
        console.error('Error fetching extension details:', error);
    } finally {
        loadingModal.value = false;
    }
};

const handleCreateRequest = async (formData) => {
    createFormSubmitting.value = true;
    formErrors.value = {};
    try {
        await axios.post(route('extensions.store'), formData);
        createModalTrigger.value = false;
        fetchData();
    } catch (error) {
        if (error.response?.data?.errors) {
            formErrors.value = error.response.data.errors;
        }
    } finally {
        createFormSubmitting.value = false;
    }
};

const handleUpdateRequest = async (formData) => {
    updateFormSubmitting.value = true;
    formErrors.value = {};
    try {
        await axios.put(route('extensions.update', { extension: itemOptions.value.extension.extension_uuid }), formData);
        editModalTrigger.value = false;
        fetchData();
    } catch (error) {
        if (error.response?.data?.errors) {
            formErrors.value = error.response.data.errors;
        }
    } finally {
        updateFormSubmitting.value = false;
    }
};

const handleSingleItemDeleteRequest = async (route) => {
    if (!confirm('Are you sure you want to delete this extension?')) {
        return;
    }
    try {
        await axios.delete(route);
        fetchData();
    } catch (error) {
        console.error('Error deleting extension:', error);
    }
};

const handleBulkActionRequest = async (action) => {
    if (selectedItems.value.length === 0) return;

    if (action === 'delete') {
        if (!confirm(`Are you sure you want to delete ${selectedItems.value.length} extensions?`)) {
            return;
        }
        try {
            await axios.post(route('extensions.bulk-delete'), {
                items: selectedItems.value,
            });
            handleClearSelection();
            fetchData();
        } catch (error) {
            console.error('Error bulk deleting extensions:', error);
        }
    } else if (action === 'enable' || action === 'disable') {
        try {
            await axios.post(route('extensions.bulk-update'), {
                items: selectedItems.value,
                enabled: action === 'enable',
            });
            handleClearSelection();
            fetchData();
        } catch (error) {
            console.error('Error bulk updating extensions:', error);
        }
    }
};

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = data.value.data.map(item => item.extension_uuid);
    } else {
        selectedItems.value = [];
    }
};

const handleSelectAll = () => {
    selectAll.value = true;
    selectedItems.value = data.value.data.map(item => item.extension_uuid);
};

const handleClearSelection = () => {
    selectAll.value = false;
    selectPageItems.value = false;
    selectedItems.value = [];
};

const handleModalClose = () => {
    createModalTrigger.value = false;
    editModalTrigger.value = false;
    bulkUpdateModalTrigger.value = false;
    formErrors.value = {};
};

const handleImportButtonClick = () => {
    // TODO: Implement import functionality
    console.log('Import button clicked');
};

const handleBulkDelete = () => {
    if (selectedItems.value.length === 0) return;
    
    if (confirm('Are you sure you want to delete the selected extensions?')) {
        router.delete(route('extensions.bulk-destroy'), {
            data: { extensions: selectedItems.value },
            preserveScroll: true,
            onSuccess: () => {
                selectedItems.value = [];
            }
        });
    }
};

const handleFormSubmit = (formData) => {
    if (formData.extension_uuid) {
        // Update existing extension
        router.put(route('extensions.update', formData.extension_uuid), formData, {
            preserveScroll: true,
            onSuccess: () => {
                editModalTrigger.value = false;
                currentExtension.value = null;
            }
        });
    } else {
        // Create new extension
        router.post(route('extensions.store'), formData, {
            preserveScroll: true,
            onSuccess: () => {
                createModalTrigger.value = false;
            }
        });
    }
};
</script> 