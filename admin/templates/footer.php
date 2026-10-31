<?php

declare(strict_types=1);

/** Kết thúc khung trang quản trị + modal đăng xuất + nạp JS. */
?>
            </div><!-- /.container-fluid -->
        </div><!-- /#content -->

        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto"><span>&copy; <?= date('Y') ?> <?= e(setting('salon_name')) ?> – Hệ thống quản trị</span></div>
            </div>
        </footer>
    </div>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalTitle">Đăng xuất?</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Đóng"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">Chọn “Đăng xuất” nếu bạn muốn kết thúc phiên làm việc hiện tại.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Hủy</button>
                <button class="btn btn-primary" type="submit">Đăng xuất</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= e(CDN_JQUERY_JS) ?>"></script>
<script src="<?= e(CDN_BOOTSTRAP_JS) ?>"></script>
<script src="<?= e(url('admin/assets/js/sb-admin-2.min.js')) ?>"></script>
<script src="<?= e(url('admin/assets/js/kitty-admin.js')) ?>"></script>
</body>
</html>
