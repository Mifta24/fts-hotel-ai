<?php

namespace App\Core\Member\Mail;

use App\Core\Member\Contracts\MemberTenant;
use App\Core\Member\Services\LoginCodeService;
use App\Core\Member\Support\MemberLabels;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent straight away, not queued: the guest is waiting on it with the sign-in
 * page open, and a code that arrives late is a code that has expired.
 */
class MemberLoginCodeMail extends Mailable
{
    public function __construct(
        public readonly MemberTenant $tenant,
        public readonly string $code,
        public readonly string $language,
    ) {}

    public function envelope(): Envelope
    {
        $brand = $this->tenant->memberBrandName();
        $replyTo = $this->tenant->memberReplyTo();

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $brand),
            replyTo: $replyTo ? [new Address($replyTo, $brand)] : [],
            subject: MemberLabels::fill(MemberLabels::for($this->language)['mail_subject'], ['brand' => $brand]),
        );
    }

    public function content(): Content
    {
        $labels = MemberLabels::for($this->language);
        $replace = ['brand' => $this->tenant->memberBrandName(), 'minutes' => LoginCodeService::TTL_MINUTES];

        return new Content(markdown: 'mail.member-login-code', with: [
            'intro' => MemberLabels::fill($labels['mail_intro'], $replace),
            'expiry' => MemberLabels::fill($labels['mail_expiry'], $replace),
            'ignore' => $labels['mail_ignore'],
            'code' => $this->code,
        ]);
    }
}
