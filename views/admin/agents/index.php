<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Agent Settings</h1>
        <p class="text-muted mb-0">Configure agent display names and settings</p>
    </div>
    <?php if ($this->can('admin.agents.manage')): ?>
    <form action="/areports/admin/agents/sync" method="POST" class="d-inline">
        <?= $this->csrf() ?>
        <button type="submit" class="btn btn-success">
            <i class="fas fa-sync me-1"></i> Sync Agents
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Agent ID</th>
                        <th>Display Name</th>
                        <th>Team</th>
                        <th class="text-center">Wrap-up Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $agent): ?>
                    <tr>
                        <td><code><?= $this->e($agent['extension']) ?></code></td>
                        <td><strong><?= $this->e($agent['display_name']) ?></strong></td>
                        <td><?= $this->e($agent['team'] ?? '-') ?></td>
                        <td class="text-center"><?= $agent['wrap_up_time'] ?>s</td>
                        <td>
                            <?php if ($this->can('admin.agents.manage')): ?>
                            <a href="/areports/admin/agents/<?= $agent['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($agents)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No agents configured. Click "Sync Agents" to import from Asterisk.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
