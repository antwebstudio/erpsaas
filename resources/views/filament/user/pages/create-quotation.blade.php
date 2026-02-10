<x-filament-panels::page>
    <div x-data="renoWizard(@js($data))" class="w-full mx-auto border border-gray-200 dark:border-gray-700 shadow-sm bg-white dark:bg-gray-900 text-gray-950 dark:text-white rounded-xl" style="padding-bottom: 5rem;" x-cloak>
        
        <!-- Header -->
        <div class="border-b border-gray-200 dark:border-gray-700 p-6 flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-bold uppercase tracking-tighter">User Quotation Builder</h1>
                <p class="text-sm mt-1 text-gray-500 dark:text-gray-400">Multi-Level Scope Selection System</p>
            </div>
            <div class="text-right">
                 <span class="bg-gray-950 dark:bg-white text-white dark:text-gray-950 px-3 py-1 text-xs font-bold rounded-full" x-text="'STEP ' + currentStepLabel"></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="px-6 py-4">

        <!-- Step 0: Client Selection -->
        <div x-show="step === 0" x-transition>
            <h2 class="text-xl font-bold mb-6 text-gray-900 dark:text-white">1. Select Client</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <template x-for="client in data.clients" :key="client.id">
                    <label 
                        :class="data.client_id == client.id ? 'bg-gray-100 dark:bg-gray-800 border-primary-500 border-2' : 'border-gray-200 dark:border-gray-700'"
                        class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <input type="radio" name="client_id" :value="client.id" x-model="data.client_id" @change="updateClientName(client.name)" class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                        <span class="font-bold text-sm text-gray-900 dark:text-white" x-text="client.name"></span>
                    </label>
                </template>
            </div>
        </div>

        <!-- Step 1: Main Work Scopes (Level 1) -->
        <div x-show="step === 1" x-transition>
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">2. Select Work Scopes</h2>
                <div x-show="data.client_name" class="text-sm font-semibold bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 px-3 py-1 rounded-full border border-primary-100 dark:border-primary-800">
                    Client: <span x-text="data.client_name"></span>
                </div>
            </div>
            
            <div class="columns-2 gap-2">
                <template x-for="(scope, index) in data.scopes" :key="index">
                    <label 
                        :class="scope.selected ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 border-gray-900 dark:border-white' : 'border-gray-200 dark:border-gray-700'"
                        class="flex items-start mb-3 gap-2 p-2 border rounded cursor-pointer hover:bg-gray-900 dark:hover:bg-gray-100 dark:hover:text-gray-900 transition-colors hover:text-white">
                        <input type="checkbox" x-model="scope.selected" class="mt-0.5 h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600">
                        <div>
                            <span class="font-bold text-sm" x-text="scope.name"></span>
                        </div>
                    </label>
                </template>
            </div>
        </div>

        <!-- Step 2 and beyond are skipped as per request -->
        
        </div><!-- End Content Area -->

        <!-- Fixed Footer with Navigation Buttons -->
        <div class="fixed bottom-0 left-0 right-0 border-t border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-900 shadow-lg z-50">
            <div class="w-full mx-auto">
                <!-- Step 0 Buttons -->
                <div x-show="step === 0" class="flex justify-end">
                    <button @click="step = 1" 
                            :disabled="!data.client_id"
                            :class="!data.client_id ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-900 dark:hover:bg-gray-100 hover:text-white dark:hover:text-gray-900'"
                            class="px-8 py-3 border border-gray-900 dark:border-white text-gray-900 dark:text-white font-bold uppercase transition-all rounded-lg">
                        Next: Select Scopes →
                    </button>
                </div>

                <!-- Step 1 Buttons -->
                <div x-show="step === 1" class="flex justify-between">
                    <button @click="step = 0" x-show="!urlHasClient" class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-xs uppercase font-bold hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">← Back</button>
                    <div x-show="urlHasClient"></div>
                    <button @click="submitQuotation" 
                            :disabled="!hasSelectedScopes"
                            :class="!hasSelectedScopes ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-green-600 hover:bg-green-700 hover:-translate-y-1'"
                            class="px-10 py-4 text-white font-bold uppercase text-lg shadow-lg rounded-xl transition-all">
                        Create & Continue →
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
    function renoWizard(initialData) {
        return {
            step: initialData.initial_step || 0,
            data: initialData,
            
            get urlHasClient() {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.has('client') || urlParams.has('estimate_id');
            },

            get currentStepLabel() {
                return ['Client Selection', 'Scope Selection'][this.step] || 'Quotation Builder';
            },

            get hasSelectedScopes() {
                return this.data.scopes.some(s => s.selected);
            },

            updateClientName(name) {
                this.data.client_name = name;
            },

            submitQuotation() {
                this.$wire.data = this.data;
                this.$wire.create();
            }
        }
    }
    </script>
</x-filament-panels::page>
