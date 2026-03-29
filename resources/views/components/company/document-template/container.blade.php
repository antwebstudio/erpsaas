@props([
    'preview' => false,
    'backgroundImage' => null,
])

<div
    @class([
        'doc-template-container flex justify-center',
    ])
>
    <style>
        /* Force light mode colors for the entire document subtree, even in dark mode */
        .doc-template-paper, 
        .doc-template-paper * {
            --tw-text-opacity: 1 !important;
            color: rgb(0 0 0 / var(--tw-text-opacity)) !important;
            border-color: rgb(229 231 235 / var(--tw-border-opacity)) !important;
        }

        /* Whitelist for elements that SHOULD be white (e.g., table headers) */
        .doc-template-paper .text-white,
        .doc-template-paper .text-white * {
            color: #ffffff !important;
        }

        /* Common Gray variants from Tailwind */
        .doc-template-paper .text-gray-600 { color: #4b5563 !important; }
        .doc-template-paper .text-gray-700 { color: #374151 !important; }
        .doc-template-paper .text-gray-500 { color: #6b7280 !important; }

        /* Background overrides */
        .doc-template-paper .bg-gray-100 { background-color: #f3f4f6 !important; }
        .doc-template-paper .bg-gray-50 { background-color: #f9fafb !important; }

        /* Ensure paper background stays white */
        .doc-template-paper {
            background-color: #ffffff !important;
        }
    </style>

    <div class="max-w-full overflow-x-auto shadow-xl ring-1 ring-gray-950/5">
        <div
            @class([
                'doc-template-paper bg-[#ffffff] overflow-y-auto',
                'w-[51.25rem] h-[64rem]' => ! $preview,
                'w-[48rem] min-h-[61.75rem] preview' => $preview,
            ])
            @if($backgroundImage)
                style="background-image: url('{{ $backgroundImage }}'); background-size: cover; background-position: center; background-repeat: no-repeat;"
            @endif
        >
            {{ $slot }}
        </div>
    </div>
</div>
