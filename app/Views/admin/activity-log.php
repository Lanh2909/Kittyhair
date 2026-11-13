<?php

declare(strict_types=1);

use App\Helpers\Format;
use App\Helpers\IpAddress;
use App\Helpers\SearchForm;

/**
 * Nhật ký hoạt động (TV4). Biến: $actors, $actor, $keyword, $rows, $pager, $actorBadge.
 */
?>
<h1 class="h3 mb-3 text-gray-800">Nhật ký hoạt động</h1>
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="filter-pills">
            <?php foreach ($actors as $key => $label): ?>
                <a href="<?= e('?' . http_build_query(['actor' => $key, 'q' => $keyword])) ?>" class="<?= $actor === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <?= SearchForm::render($keyword, 'Tìm theo nội dung, người thực hiện, hành động hoặc IP (vd: 127.0.0.1, localhost)…', ['actor' => $actor === 'all' ? '' : $actor]) ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Nội dung</th><th>IP</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">Chưa có hoạt động nào.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(Format::datetime($row['created_at'])) ?></td>
                        <td><span class="badge <?= e($actorBadge[$row['actor_type']] ?? 'badge-secondary') ?>"><?= e($actors[$row['actor_type']] ?? $row['actor_type']) ?></span> <?= e($row['actor_name']) ?></td>
                        <td><code><?= e($row['action']) ?></code></td>
                        <td><?= e($row['description']) ?></td>
                        <td class="small"><?= IpAddress::html((string) $row['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <?= $pager->summary() ?>
            <?= $pager->links(['q' => $keyword, 'actor' => $actor === 'all' ? '' : $actor]) ?>
        </div>
    </div>
</div>
