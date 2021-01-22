<div class="container">
    <div class="row">
        <div class="col-sm-12 col-md-12 col-lg-12 main">
            <h3><?= $title ?></h3>
            <div class="pull-right" style="margin-bottom: 10px;">
                <?php echo form_open("auth/users_list", 'class="form-inline" role="form"'); ?>

                <div class="form-group">
                    <?php echo form_input(array('name' => 'firstname', 'id' => 'fname', 'class' => "form-control", 'placeholder' => lang('index_fname_th'))); ?>
                </div>

                <div class="form-group">
                    <?php echo form_input(array('name' => 'lastname', 'id' => 'lname', 'class' => "form-control", 'placeholder' => lang('index_lname_th'))); ?>
                </div>

                <div class="form-group">
                    <?php echo form_input(array('name' => 'phone', 'id' => 'phone', 'class' => "form-control", 'placeholder' => lang('index_phone_th'))); ?>
                </div>
                <div class="form-group">
                    <?php echo form_dropdown("status", array("" => "Choose status", 1 => "Active", 0 => "Inactive"), NULL, 'class="form-control"'); ?>
                </div>

                <div class="form-group">
                    <div class="input-group">
                        <?php echo form_submit("search", "Search", 'class="btn btn-primary"'); ?>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>

            <div class="col-sm-12">
                <table class="table table-striped table-responsive table-hover">
                    <tr>
                        <th><?php echo lang('index_fname_th'); ?></th>
                        <th><?php echo lang('index_lname_th'); ?></th>
                        <th><?php echo lang('index_phone_th'); ?></th>
                        <th><?php echo lang('index_created_on_th'); ?></th>
                        <th><?php echo lang('index_last_login_th'); ?></th>
                        <th colspan="2" class="text-center"><?php echo lang('index_status_th'); ?></th>
                    </tr>

                    <?php
                    $serial = 1;
                    foreach ($users as $user):?>
                        <tr>
                            <td><?php echo $user->first_name; ?></td>
                            <td><?php echo $user->last_name; ?></td>
                            <td><?php echo $user->phone; ?></td>
                            <td><?php echo date('d-m-Y H:i:s', $user->created_on); ?></td>
                            <td><?php echo date('d-m-Y H:i:s', $user->last_login); ?></td>
                            <td><?php echo ($user->active) ? anchor("auth/deactivate/" . $user->id, lang('index_active_link'), array("class" => 'btn btn-info btn-xs')) :
                                    anchor("auth/activate/" . $user->id, lang('index_inactive_link'), array("class" => 'btn btn-warning btn-xs')); ?></td>
                            <td><?php echo anchor("auth/edit_user/" . $user->id, lang('edit_user_heading'), array("class" => 'btn btn-primary btn-xs')); ?></td>
                            </td>
                        </tr>
                        <?php
                        $serial++;
                    endforeach; ?>
                </table>
                <?php if (!empty($links)): ?>
                    <div class="widget-foot">
                        <?= $links ?>
                        <div class="clearfix"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

