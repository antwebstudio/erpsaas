@props([
    'preview' => false,
    'backgroundImage' => null,
])

<div
    @class([
        'doc-template-container flex justify-center',
    ])
>
    <div class="max-w-full overflow-x-auto shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10">
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
