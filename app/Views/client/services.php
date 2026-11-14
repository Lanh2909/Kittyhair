<?php

declare(strict_types=1);

use App\Helpers\Format;
use App\Helpers\SearchForm;

/**
 * Bảng giá công khai. Biến: $categories, $rows, $keyword, $categoryId, $pager, $keep.
 */
?>
<section class="page-wrap">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">Minh bạch – rõ ràng</span>
            <h1 class="h2" style="font-size:2.3rem;">Bảng giá dịch vụ</h1>
            <div class="divider"></div>
        </div>

        <div class="filter-tabs">
            <a href="<?= e('?' . http_build_query(['q' => $keyword])) ?>" class="<?= $categoryId === 0 ? 'active' : '' ?>">Tất cả</a>
            <?php foreach ($categories as $category): ?>
                <a href="<?= e('?' . http_build_query(['q' => $keyword, 'category' => $category['category_id']])) ?>" class="<?= $categoryId === (int) $category['category_id'] ? 'active' : '' ?>"><?= e($category['category_name']) ?></a>
            <?php endforeach; ?>
        </div>
        <?= SearchForm::render($keyword, 'Tìm dịch vụ: cắt, nhuộm, uốn, phục hồi…', ['category' => $categoryId ?: '']) ?>

        <?php if (!$rows): ?>
            <div class="panel empty-state"><i class="fas fa-search"></i><p class="mb-0">Không tìm thấy dịch vụ phù hợp.</p></div>
        <?php endif; ?>

        <div class="row">
            <?php foreach ($rows as $service): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="price-card d-flex flex-column">
                        <small class="text-primary font-weight-bold text-uppercase"><?= e($service['category_name']) ?></small>
                        <div class="d-flex mt-2">
                            <?php if ($thumb = $uploads->url('services', $service['image'])): ?>
                                <img class="thumb mr-3" style="width:64px;height:64px;border-radius:12px;object-fit:cover;" src="<?= e($thumb) ?>" alt="<?= e($service['service_name']) ?>">
                            <?php endif; ?>
                            <div>
                                <h2 class="h5 mb-1"><?= e($service['service_name']) ?></h2>
                                <p class="text-muted small mb-0"><?= e($service['service_description']) ?></p>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-end mt-auto pt-3">
                            <div>
                                <div class="text-primary font-weight-bold" style="font-size:1.2rem;"><?= e(Format::money($service['service_price'])) ?></div>
                                <small class="text-muted"><i class="far fa-clock"></i> <?= e(Format::duration((int) $service['service_duration'])) ?></small>
                            </div>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('booking.php?service=' . (int) $service['service_id'])) ?>">Đặt ngay</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap mt-2">
            <?= $pager->summary() ?>
            <?= $pager->links($keep) ?>
        </div>
    </div>
</section>
