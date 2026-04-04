<x-mail::message>
# {{ $subjectString ?? 'Document' }}

{!! nl2br(e($content)) !!}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
