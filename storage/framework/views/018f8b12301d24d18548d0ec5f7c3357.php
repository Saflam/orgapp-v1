<div>
    <div class="page-head">
        <div><h1>WhatsApp message history</h1><div class="muted">Searchable, organization-filtered history with delivery and webhook audit details. Message bodies and OTP values are not displayed.</div></div>
        <a class="btn secondary" href="/system/whatsapp-usage">Back to WhatsApp usage</a>
    </div>
    <div class="long-page-layout">
        <?php echo $__env->make('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'history'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="long-page-content">
            <section class="card">
                <h2>Filter message history</h2>
                <div class="grid">
                    <div class="field"><label for="history-organization">Organization</label><select id="history-organization" wire:model.live="organizationId"><option value="">All organizations</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $organizations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organization): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($organization->id); ?>"><?php echo e($organization->name); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></div>
                    <div class="field"><label for="history-service">Service category</label><select id="history-service" wire:model.live="serviceCategory"><option value="">All categories</option><option value="otp">OTP</option><option value="utility">Utility</option><option value="marketing">Marketing</option></select></div>
                    <div class="field"><label for="history-api-status">API status</label><select id="history-api-status" wire:model.live="apiStatus"><option value="">All API statuses</option><option value="accepted">Accepted</option><option value="failed">Failed</option></select></div>
                    <div class="field"><label for="history-status">Delivery status</label><select id="history-status" wire:model.live="deliveryStatus"><option value="">All delivery statuses</option><option value="awaiting">Awaiting webhook</option><option value="sent">Sent</option><option value="delivered">Delivered</option><option value="read">Read</option><option value="failed">Failed</option></select></div>
                    <div class="field"><label for="history-search">Search</label><input id="history-search" type="search" wire:model.live.debounce.400ms="search" placeholder="Meta message ID, title, phone"></div>
                    <div class="date-filter-row" style="display:grid;grid-template-columns:repeat(2,minmax(0,160px));gap:12px;max-width:332px;align-items:end">
                        <div class="field" style="min-width:0"><label for="history-from">From</label><input id="history-from" type="date" wire:model.live="fromDate" style="width:100%;min-width:0"></div>
                        <div class="field" style="min-width:0"><label for="history-to">To</label><input id="history-to" type="date" wire:model.live="toDate" style="width:100%;min-width:0"></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <h2>Message records</h2>
                <p class="muted"><?php echo e(number_format($messages->total())); ?> records match these filters. Phone numbers are masked; OTP values and message bodies are not shown.</p>
                <div class="table-wrap"><table><thead><tr><th>Time</th><th>Organization</th><th>Recipient</th><th>Category</th><th>Channel</th><th>API status</th><th>Delivery</th><th>Attempts</th><th>Meta message ID</th><th>Details</th></tr></thead><tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'wa-message-'.e($message->id).''; ?>wire:key="wa-message-<?php echo e($message->id); ?>">
                            <td><?php echo e($message->accepted_at ?: $message->created_at); ?></td>
                            <td><?php echo e($message->organization_name ?? 'Unattributed / central'); ?></td>
                            <td><?php echo e($message->recipient_masked); ?></td>
                            <td><?php echo e(strtoupper($message->service_category)); ?></td>
                            <td><?php echo e(ucfirst($message->channel_source)); ?></td>
                            <td><?php echo e(ucfirst($message->api_status)); ?></td>
                            <td><?php echo e($message->delivery_status ? ucfirst($message->delivery_status) : 'Awaiting webhook'); ?></td>
                            <td><?php echo e($message->retry_count); ?></td>
                            <td><?php echo e($message->provider_message_id ? \Illuminate\Support\Str::limit($message->provider_message_id, 24) : 'Not assigned'); ?></td>
                            <td><button type="button" class="btn secondary" wire:click="toggleAudit(<?php echo e($message->id); ?>)"><?php echo e($selectedAuditUsageId === $message->id ? 'Hide audit' : 'View audit'); ?></button></td>
                        </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedAuditUsageId === $message->id): ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'wa-message-audit-'.e($message->id).''; ?>wire:key="wa-message-audit-<?php echo e($message->id); ?>"><td colspan="10">
                                <div class="card">
                                    <h3>Delivery and audit details</h3>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($message->delivery_error_detail): ?><p><strong>Latest failure detail:</strong> <?php echo e($message->delivery_error_detail); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <p><strong>Accepted:</strong> <?php echo e($message->accepted_at ?: 'Not recorded'); ?> · <strong>Delivered:</strong> <?php echo e($message->delivered_at ?: 'Not recorded'); ?></p>
                                    <p><strong>Pricing category:</strong> <?php echo e($message->meta_pricing_category ?: 'Not reported'); ?> · <strong>Billable:</strong> <?php echo e(is_null($message->meta_billable) ? 'Not reported' : ($message->meta_billable ? 'Yes' : 'No')); ?></p>
                                    <h4>Meta webhook event history</h4>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($auditEvents->isEmpty()): ?>
                                        <p class="muted">No matching webhook events have been recorded for this message ID.</p>
                                    <?php else: ?>
                                        <div class="table-wrap"><table><thead><tr><th>Provider timestamp</th><th>Status</th><th>Error code</th><th>Error</th><th>Pricing category</th><th>Billable</th><th>Processed at</th></tr></thead><tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $auditEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'wa-audit-event-'.e($event->status).'-'.e($loop->index).''; ?>wire:key="wa-audit-event-<?php echo e($event->status); ?>-<?php echo e($loop->index); ?>"><td><?php echo e($event->provider_timestamp ?: 'Not supplied'); ?></td><td><?php echo e(ucfirst($event->status)); ?></td><td><?php echo e($event->error_code ?: '—'); ?></td><td><?php echo e($event->error_title ?: '—'); ?></td><td><?php echo e($event->pricing_category ?: '—'); ?></td><td><?php echo e(is_null($event->billable) ? '—' : ($event->billable ? 'Yes' : 'No')); ?></td><td><?php echo e($event->processed_at ?: '—'); ?></td></tr>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </tbody></table></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="10" class="empty">No WhatsApp message records match these filters.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody></table></div>
                <div class="pagination-wrap"><?php echo e($messages->links()); ?></div>
            </section>
        </div>
    </div>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Core\resources/views/livewire/system/whatsapp-message-history.blade.php ENDPATH**/ ?>