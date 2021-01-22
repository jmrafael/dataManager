<div class="container">
    <div class="row">
        <?php echo form_open_multipart('xform/add_new/' . $project_id, 'class="form-vertical" role="form"'); ?>
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
            <div id="header-title">
                <h3 class="title">Add new form</h3>
            </div>

            <!-- Breadcrumb -->
            <ol class="breadcrumb">
                <li><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                <li class="active">Add new form</li>
            </ol>

            <?php
            if ($this->session->flashdata('message') != '') {
                echo '<div class="success_message">' . $this->session->flashdata('message') . '</div>';
            } ?>

            <ul class="nav nav-tabs">
                <li class="active"><a data-toggle="tab" href="#form-details">Edit form details</a></li>
                <li><a data-toggle="tab" href="#access-permissions">Manage Access Permissions</a></li>
            </ul>

            <div class="tab-content">
                <div id="form-details" class="tab-pane fade in active">
                    <div class="">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="">Form details</h3>
                            </div>
                            <div class="panel-body">
                                <div class="form-group">
                                    <label><?php echo $this->lang->line("label_form_title") ?> <span>*</span></label>
                                    <input type="text" name="title" placeholder="Enter form title"
                                           class="form-control"
                                           value="<?php echo set_value('title'); ?>">
                                </div>
                                <?php echo form_error('title'); ?>

                                <div class="form-group">
                                    <label for=""><?php echo $this->lang->line("label_form_xml_file") ?>
                                        <span>*</span></label>
                                    <?= form_upload("userfile", "", 'class="form-control"') ?>
                                </div>

                                <div class="form-group">
                                    <label for="campus"><?php echo $this->lang->line("label_description") ?> :</label>
                        <textarea class="form-control" name="description" rows="5"
                                  id="description"><?php echo set_value('description'); ?></textarea>
                                </div>
                                <?php echo form_error('description'); ?>

                                <div class="form-group">
                                    <label for="campus"><?php echo $this->lang->line("label_access") ?> :</label>
                                    <?php echo form_dropdown("access", array("private" => "Private", "public" => "Public"), set_value("access", ""), 'class="form-control"'); ?>
                                </div>
                                <?php echo form_error('access'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="access-permissions" class="tab-pane fade">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="">Manage Access Permissions</h3>
                        </div>
                        <div class="panel-body">
                            <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4">
                                <h4>Group Permissions</h4>
                                <?php
                                foreach ($group_perms as $key => $value) {
                                    echo form_checkbox("perms[]", $key, FALSE);
                                    echo ucfirst($value) . "</br>";
                                } ?>
                            </div>
                            <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4">
                                <h4>User Permissions</h4>
                                <?php
                                foreach ($user_perms as $key => $value) {
                                    echo form_checkbox("perms[]", $key, FALSE);
                                    echo ucfirst($value) . "</br>";
                                } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-lg">Save</button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>