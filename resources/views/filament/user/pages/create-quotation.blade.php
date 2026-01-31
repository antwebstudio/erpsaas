<x-filament-panels::page>
    <div x-data="renoWizard(@js($data))" class="w-full mx-auto border border-gray-200 dark:border-gray-700 p-6 shadow-sm bg-white dark:bg-gray-900 text-gray-950 dark:text-white min-h-screen rounded-xl">
        
        <!-- Header -->
        <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6 flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-bold uppercase tracking-tighter">User Quotation Builder</h1>
                <p class="text-sm mt-1 text-gray-500 dark:text-gray-400">Multi-Level Scope Selection System</p>
            </div>
            <div class="text-right">
                 <span class="bg-gray-950 dark:bg-white text-white dark:text-gray-950 px-3 py-1 text-xs font-bold rounded-full" x-text="'STEP ' + currentStepLabel"></span>
            </div>
        </div>

        <!-- Step 0: Main Work Scopes (Level 1) -->
        <div x-show="step === 0" x-transition>
            <h2 class="text-xl font-bold mb-6 text-gray-900 dark:text-white">1. Select Work Scopes</h2>
            
            <div class="grid grid-cols-1 gap-4">
                <template x-for="(scope, index) in data.scopes" :key="index">
                    <label class="flex items-start gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <input type="checkbox" x-model="scope.selected" class="mt-1 h-5 w-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600">
                        <div>
                            <span class="font-bold text-lg text-gray-900 dark:text-white" x-text="scope.name"></span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="scope.descriptions.length + ' Description Categories Available'"></p>
                        </div>
                    </label>
                </template>
            </div>

            <div class="mt-8 text-right">
                <button @click="goToLevel2()" 
                        :disabled="!hasSelectedScopes"
                        :class="!hasSelectedScopes ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-900 dark:hover:bg-gray-100 hover:text-white dark:hover:text-gray-900'"
                        class="px-8 py-3 border border-gray-900 dark:border-white text-gray-900 dark:text-white font-bold uppercase transition-all rounded-lg">
                    Next: Select Descriptions →
                </button>
            </div>
        </div>

        <!-- Step 1: Scope Descriptions (Level 2) -->
        <div x-show="step === 1" x-transition>
            <h2 class="text-xl font-bold mb-2 text-gray-900 dark:text-white">2. Select Descriptions</h2>
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Select the description categories for each scope. You can edit the description text.</p>

            <div class="space-y-8">
                <template x-for="(scope, sIndex) in data.scopes" :key="scope.id">
                    <div x-show="scope.selected" class="border border-gray-200 dark:border-gray-700 rounded-lg p-5 relative mt-6">
                        <div class="absolute -top-3 left-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 px-2 text-xs font-bold uppercase rounded" x-text="scope.name"></div>
                        
                        <div class="mt-2 space-y-4">
                            <template x-for="(desc, dIndex) in scope.descriptions" :key="desc.id">
                                <div class="flex items-start gap-3 p-3 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <input type="checkbox" x-model="desc.selected" class="mt-1.5 h-5 w-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="w-full">
                                        <textarea x-model="desc.text" 
                                                  rows="2"
                                                  class="w-full p-2 text-sm bg-transparent border-none focus:ring-0 text-gray-900 dark:text-gray-100 resize-y placeholder-gray-400"
                                                  placeholder="Description text..."></textarea>
                                        <div class="text-[10px] text-gray-400 dark:text-gray-500 text-right mt-1" x-text="desc.items.length + ' sub-options available'"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-8 flex justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                <button @click="step = 0" class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-xs uppercase font-bold hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">← Back</button>
                <button @click="goToLevel3()" 
                        :disabled="!hasSelectedDescriptions"
                         :class="!hasSelectedDescriptions ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-900 dark:hover:bg-gray-100 hover:text-white dark:hover:text-gray-900'"
                        class="px-6 py-2 border border-gray-900 dark:border-white text-gray-900 dark:text-white text-sm uppercase font-bold transition-all rounded-lg">
                    Next: Select Items & Details →
                </button>
            </div>
        </div>

        <!-- Step 2: Item Details (Level 3) -->
        <div x-show="step === 2" x-transition>
            <h2 class="text-xl font-bold mb-2 text-gray-900 dark:text-white">3. Configure Items</h2>
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Select specific items and enter Quantity, UOM, and Unit Price.</p>

            <div class="space-y-10">
                <template x-for="scope in data.scopes" :key="scope.id">
                    <div x-show="scope.selected && scope.descriptions.some(d => d.selected)">
                        <h3 class="font-bold text-lg mb-4 text-primary-600 dark:text-primary-400" x-text="scope.name"></h3>
                        
                        <div class="space-y-6 pl-4 border-l-2 border-gray-200 dark:border-gray-700">
                            <template x-for="desc in scope.descriptions" :key="desc.id">
                                <div x-show="desc.selected" class="mb-6">
                                    <div class="font-bold text-sm bg-gray-50 dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-700 mb-4 whitespace-pre-line text-gray-800 dark:text-gray-200" x-text="desc.text"></div>
                                    
                                    <div class="grid gap-3">
                                        <template x-for="(item, iIndex) in desc.items" :key="item.id">
                                            <div class="border border-gray-200 dark:border-gray-700 p-4 rounded-lg flex flex-col md:flex-row gap-4 items-start md:items-center bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition-shadow">
                                                 <!-- Selection & Name Edit -->
                                                <div class="flex items-start gap-3 flex-grow w-full md:w-auto">
                                                    <input type="checkbox" x-model="item.selected" class="mt-1 h-5 w-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600">
                                                    <input type="text" x-model="item.name" class="w-full text-sm bg-transparent border-0 border-b border-gray-300 dark:border-gray-600 focus:border-primary-500 focus:ring-0 px-0 py-1 text-gray-900 dark:text-white" placeholder="Item Name">
                                                </div>

                                                <!-- Inputs (Only show if selected) -->
                                                <div x-show="item.selected" class="grid grid-cols-3 gap-3 w-full md:w-auto flex-shrink-0" x-transition>
                                                    <div>
                                                        <label class="text-[9px] uppercase font-bold block text-gray-500 dark:text-gray-400">Qty</label>
                                                        <input type="number" x-model="item.qty" class="w-20 p-1 text-right text-sm bg-transparent border-0 border-b border-gray-300 dark:border-gray-600 focus:border-primary-500 focus:ring-0 text-gray-900 dark:text-white">
                                                    </div>
                                                    <div>
                                                        <label class="text-[9px] uppercase font-bold block text-gray-500 dark:text-gray-400">UOM</label>
                                                        <input type="text" x-model="item.uom" class="w-20 p-1 text-center text-sm uppercase bg-transparent border-0 border-b border-gray-300 dark:border-gray-600 focus:border-primary-500 focus:ring-0 text-gray-900 dark:text-white">
                                                    </div>
                                                    <div>
                                                        <label class="text-[9px] uppercase font-bold block text-gray-500 dark:text-gray-400">Price</label>
                                                        <input type="number" x-model="item.price" class="w-24 p-1 text-right text-sm bg-transparent border-0 border-b border-gray-300 dark:border-gray-600 focus:border-primary-500 focus:ring-0 text-gray-900 dark:text-white">
                                                    </div>
                                                </div>
                                                
                                                <!-- Subtotal -->
                                                <div x-show="item.selected" class="w-28 text-right font-mono font-bold text-sm text-gray-900 dark:text-white">
                                                    <span x-text="'$' + (item.qty * item.price).toLocaleString()"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-8 flex justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                <button @click="step = 1" class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-xs uppercase font-bold hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">← Back</button>
                <button @click="goToConfirmation()" 
                        class="px-8 py-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 uppercase font-bold text-sm shadow-md rounded-lg">
                    Review & Confirm →
                </button>
            </div>
        </div>

        <!-- Step 3: Confirmation Page -->
        <div x-show="step === 3" x-transition>
            <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white border-l-4 border-primary-500 pl-4">Quotation Confirmation</h2>

            <!-- Final Editable Review -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 mb-8 bg-gray-50 dark:bg-gray-800/50">
                <template x-for="scope in data.scopes" :key="scope.id">
                    <div x-show="scope.selected && hasSelectedItemsInScope(scope)" class="mb-8 last:mb-0">
                        <h3 class="text-lg font-bold uppercase text-gray-900 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-2" x-text="scope.name"></h3>
                        
                        <template x-for="desc in scope.descriptions" :key="desc.id">
                            <div x-show="desc.selected && desc.items.some(i => i.selected)" class="mb-6 pl-4">
                                <p class="text-sm font-semibold mb-2 whitespace-pre-wrap text-gray-700 dark:text-gray-300" x-text="desc.text"></p>
                                
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs md:text-sm text-left">
                                        <thead class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                                            <tr>
                                                <th class="py-2 w-1/2">Item Description</th>
                                                <th class="py-2 text-center">Qty</th>
                                                <th class="py-2 text-center">UOM</th>
                                                <th class="py-2 text-right">Unit Price</th>
                                                <th class="py-2 text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-gray-900 dark:text-gray-100">
                                            <template x-for="item in desc.items" :key="item.id">
                                                <tr x-show="item.selected" class="border-b border-gray-200 dark:border-gray-700 last:border-0 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                                    <td class="py-2 pr-2">
                                                        <input type="text" x-model="item.name" class="w-full bg-transparent border-none focus:ring-0 p-1">
                                                    </td>
                                                    <td class="py-2 text-center">
                                                        <input type="number" x-model="item.qty" class="w-16 text-center bg-transparent border-none focus:ring-0 p-1">
                                                    </td>
                                                    <td class="py-2 text-center">
                                                        <input type="text" x-model="item.uom" class="w-12 text-center bg-transparent border-none focus:ring-0 p-1 uppercase">
                                                    </td>
                                                    <td class="py-2 text-right">
                                                        <input type="number" x-model="item.price" class="w-20 text-right bg-transparent border-none focus:ring-0 p-1">
                                                    </td>
                                                    <td class="py-2 text-right font-bold font-mono" x-text="'$' + (item.qty * item.price).toLocaleString()"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Totals & Options -->
            <div class="grid md:grid-cols-2 gap-8 mb-8">
                <div class="space-y-4">
                    <div>
                        <label class="block font-bold text-xs uppercase mb-1 text-gray-500 dark:text-gray-400">Select Client <span class="text-red-500">*</span></label>
                        <select x-model="data.client_id" class="w-full p-2 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                             <option value="">-- Select Client --</option>
                             <template x-for="client in data.clients" :key="client.id">
                                 <option :value="client.id" x-text="client.name"></option>
                             </template>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-xs uppercase mb-1 text-gray-500 dark:text-gray-400">Select Terms & Conditions Template</label>
                        <select class="w-full p-2 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                            <option>Standard Construction Terms (2024)</option>
                            <option>Short Form Terms</option>
                            <option>Commercial Renovation Terms</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-xs uppercase mb-1 text-gray-500 dark:text-gray-400">Select Quotation Template</label>
                        <select class="w-full p-2 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                            <option>Modern Clean</option>
                            <option>Detailed Technical</option>
                            <option>Compact Summary</option>
                        </select>
                    </div>
                </div>

                <div class="bg-gray-900 dark:bg-gray-800 text-white p-6 flex flex-col justify-center items-end shadow-lg rounded-xl">
                    <span class="text-sm font-normal uppercase tracking-widest mb-1 text-gray-400">Grand Total Estimate</span>
                    <span class="text-4xl font-mono font-bold" x-text="'$' + calculateGrandTotal().toLocaleString()"></span>
                </div>
            </div>

            <!-- Final Actions -->
            <div class="flex justify-between items-center pt-6 border-t border-gray-200 dark:border-gray-700">
                <button @click="step = 2" class="text-xs font-bold underline uppercase text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">Back to Edit</button>
                <button @click="submitQuotation" 
                        :disabled="!data.client_id"
                        :class="!data.client_id ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-green-600 hover:bg-green-700 hover:-translate-y-1'"
                        class="px-10 py-4 text-white font-bold uppercase text-lg shadow-lg rounded-xl transition-all">
                    Create Quotation
                </button>
            </div>
        </div>

    </div>

    <script>
    function renoWizard(initialData) {
        return {
            step: 0,
            data: initialData,

            get currentStepLabel() {
                return ['Scope Selection', 'Descriptions', 'Item Details', 'Confirmation'][this.step];
            },

            get hasSelectedScopes() {
                return this.data.scopes.some(s => s.selected);
            },

            get hasSelectedDescriptions() {
                return this.data.scopes.some(s => s.selected && s.descriptions.some(d => d.selected));
            },

            hasSelectedItemsInScope(scope) {
                 return scope.descriptions.some(d => d.selected && d.items.some(i => i.selected));
            },

            goToLevel2() {
                if(this.hasSelectedScopes) this.step = 1;
            },

            goToLevel3() {
                if(this.hasSelectedDescriptions) this.step = 2;
            },
            
            goToConfirmation() {
                this.step = 3;
            },

            calculateGrandTotal() {
                let total = 0;
                this.data.scopes.forEach(scope => {
                    if(scope.selected) {
                        scope.descriptions.forEach(desc => {
                            if(desc.selected) {
                                desc.items.forEach(item => {
                                    if(item.selected) {
                                        total += (item.qty * item.price);
                                    }
                                });
                            }
                        });
                    }
                });
                return total;
            },

            submitQuotation() {
                this.$wire.data = this.data;
                this.$wire.create();
            }
        }
    }
    </script>
</x-filament-panels::page>
