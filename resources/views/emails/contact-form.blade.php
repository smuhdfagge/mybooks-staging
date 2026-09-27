<x-mail::message>
# New Contact Form Submission

You have received a new message from the contact form on My-Books.

**From:** {{ $data['first_name'] }} {{ $data['last_name'] }}

**Email:** {{ $data['email'] }}

@if(!empty($data['phone']))
**Phone:** {{ $data['phone'] }}
@endif

**Subject:** {{ $data['subject'] }}

---

## Message

{{ $data['message'] }}

---

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
