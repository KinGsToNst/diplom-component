<?php
if (!$_SESSION['auth_logged_in']) {
    header("Location: /login");
    exit();
}
$this->layout('layout');

?>
    <main id="js-page-content" role="main" class="page-content mt-3">
        <div class="subheader">
            <h1 class="subheader-title">
                <i class='subheader-icon fal fa-sun'></i> Установить статус
            </h1>

        </div>
        <form action="/update_status/<?=$user['id']?>" method="post">
            <div class="row">
                <div class="col-xl-6">
                    <div id="panel-1" class="panel">
                        <div class="panel-container">
                            <div class="panel-hdr">
                                <h2>Установка текущего статуса</h2>
                            </div>
                            <div class="panel-content">
                                <div class="row">
                                    <div class="col-md-4">
                                        <!-- status -->

                                        <div class="form-group">
                                            <label class="form-label" for="example-select">Выберите статус</label>

                                            <select class="form-control" id="example-select"  name="status_id">
                                                <?php foreach ($status as $info): ?>
                                                    <?php if ($info["id"] == $current_user_status["status_id"]): ?>
                                                        <option value="<?php echo $info["id"] ?>" selected><?php echo $info["status_value"] ?></option>
                                                    <?php else: ?>
                                                        <option value="<?php echo $info["id"]; ?>"><?php echo $info["status_value"] ?></option>
                                                    <?php endif;
                                                    ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                    </div>
                                    <div class="col-md-12 mt-3 d-flex flex-row-reverse">
                                        <button class="btn btn-warning">Set Status</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </form>
    </main>

