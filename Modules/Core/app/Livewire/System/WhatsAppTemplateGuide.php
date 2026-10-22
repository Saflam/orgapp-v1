<?php

namespace Modules\Core\Livewire\System;

use Illuminate\View\View;
use Livewire\Component;

class WhatsAppTemplateGuide extends Component
{
    public function render(): View
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        return view('core::livewire.system.whats-app-template-guide')
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'WhatsApp template guide']);
    }
}
