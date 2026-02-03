<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- 1) Accounting -> Chart of Accounts --}}
        <x-filament::section
            icon="heroicon-o-briefcase"
            icon-color="primary"
        >
            <x-slot name="heading">
                Accounting
            </x-slot>

            <x-slot name="description">
                Manage your chart of accounts and financial records.
            </x-slot>
            
            <div class="mt-4">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Company\Pages\Reports::getUrl()"
                    class="w-full"
                >
                    Go to Accounting
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- 2) Leads Data -> LeadResource Index --}}
        <x-filament::section
            icon="heroicon-o-funnel"
            icon-color="warning"
        >
            <x-slot name="heading">
                Leads Data
            </x-slot>
            
            <x-slot name="description">
                View and manage your sales leads.
            </x-slot>

            <div class="mt-4">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Company\Resources\Sales\LeadResource::getUrl('index')"
                    class="w-full"
                    color="warning"
                >
                    View Leads
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- 3) Client Data -> ClientResource Index --}}
        <x-filament::section
            icon="heroicon-o-users"
            icon-color="success"
        >
            <x-slot name="heading">
                Client Data
            </x-slot>
            
            <x-slot name="description">
                Manage your client database.
            </x-slot>

            <div class="mt-4">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Company\Resources\Sales\ClientResource::getUrl('index')"
                    class="w-full"
                    color="success"
                >
                    View Clients
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- 4) Sales -> InvoiceResource Index --}}
        <x-filament::section
            icon="heroicon-o-banknotes"
            icon-color="info"
        >
            <x-slot name="heading">
                Sales
            </x-slot>
            
            <x-slot name="description">
                Manage invoices and sales records.
            </x-slot>

            <div class="mt-4">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Company\Resources\Sales\InvoiceResource::getUrl('index')"
                    class="w-full"
                    color="info"
                >
                    Go to Sales
                </x-filament::button>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
