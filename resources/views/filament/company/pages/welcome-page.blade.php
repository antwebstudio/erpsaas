<x-filament-panels::page>
    <style>
        .welcome-grid > .fi-section {
            height: 100%;
            display: flex !important;
            flex-direction: column !important;
        }
        .welcome-grid > .fi-section > .fi-section-content-ctn {
            flex: 1 1 auto !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .welcome-grid > .fi-section > .fi-section-content-ctn > .fi-section-content {
            flex: 1 1 auto !important;
            display: flex !important;
            flex-direction: column !important;
        }
    </style>

    <div class="welcome-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- 1) Accounting -> Chart of Accounts --}}
        <x-filament::section
            icon="heroicon-o-briefcase"
            icon-color="primary"
        >
            <x-slot name="heading">
                Accounting
            </x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage your chart of accounts and financial records.
            </p>

            <div class="mt-auto pt-4">
                <x-filament::dropdown class="w-full">
                    <x-slot name="trigger">
                        <x-filament::button
                            class="w-full"
                            icon="heroicon-m-chevron-down"
                            icon-position="after"
                        >
                            Go to Accounting
                        </x-filament::button>
                    </x-slot>

                    <x-filament::dropdown.list>
                        @foreach ($this->getCompanies() as $company)
                            <x-filament::dropdown.list.item
                                :href="\App\Filament\Company\Pages\WelcomePage::getUrl(['tenant' => $company])"
                                tag="a"
                            >
                                {{ $company->name }}
                            </x-filament::dropdown.list.item>
                        @endforeach
                    </x-filament::dropdown.list>
                </x-filament::dropdown>
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

            <p class="text-sm text-gray-500 dark:text-gray-400">
                View and manage your sales leads.
            </p>

            <div class="mt-auto pt-4">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Company\Resources\Sales\LeadResource::getUrl('index', ['tenant' => $this->getSystemCompany() ?? $this->getCompanies()->first()])"
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

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage your client database.
            </p>

            <div class="mt-auto pt-4">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Company\Resources\Sales\AllClientResource::getUrl('index', ['tenant' => $this->getSystemCompany() ?? filament()->getTenant()])"
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

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage invoices and sales records.
            </p>

            <div class="mt-auto pt-4">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Company\Resources\Sales\InvoiceResource::getUrl('index', ['tenant' => filament()->getTenant()])"
                    class="w-full"
                    color="info"
                >
                    Go to Sales
                </x-filament::button>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
