<?php if (!defined('APP_STARTED')) {
    die('Forbidden!');
} ?>
<div class="breadcrumbs">
    <div class="pull-right">
        <?php if ($html && isset($source)): ?>
            <a href="javascript:;" class="btn-black" id="toggle">Toggle source</a>
        <?php endif ?>
        <?php if ($use_pastebin): ?>
            <a href="javascript:;" class="btn-black" id="create-pastebin" title="Create public Paste on PasteBin">Create public Paste</a>
        <?php endif; ?>
		<a href="https://<?= ltrim($_SERVER['REQUEST_URI'], '/') ?>" class="btn-black" style="color: #4FA1FF;">Original page</a>
    </div>

    <?php $path = array(); ?>

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?php echo BASE_URL; ?>">
              <i class="fas fa-home"></i> wiki
            </a>
        </li>
        <?php $i = 0; ?>
        <?php foreach ($parts as $part): ?>
            <?php $path[] = $part; ?>
            <?php $url = BASE_URL . "/" . join("/", $path); ?>
            <?php $i++; ?>
            <li class="breadcrumb-item <?php echo ($i == count($parts) ? 'active' : '')?>">
                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($i == count($parts) && !$is_dir): ?>
                        <i class="far fa-file"></i>
                    <?php else: ?>
                        <i class="far fa-folder"></i>
                    <?php endif ?>
                    <?php echo $part; ?>
                </a>
            </li>
        <?php endforeach ?>
      </ol>
    </nav>

</div>

<?php if ($html): ?>
    <?php if ($use_pastebin): ?>
    <div id="pastebin-notification" class="alert" style="display:none;"></div>
    <?php endif; ?>
    <div id="render">
        <?php echo $html; ?>
    </div>
    <script>
        $('#render pre').addClass('prettyprint linenums');
        prettyPrint();

        $('#render a[href^="#"]').click(function(event) {
            event.preventDefault();
            document.location.hash = $(this).attr('href').replace('#', '');
        });
    </script>
<?php endif ?>

<?php if (isset($source)): ?>
    <?php if ($use_pastebin): ?>
    <div id="pastebin-notification" class="alert" style="display:none;"></div>
    <?php endif; ?>
    <div id="source">
        <?php if (ENABLE_EDITING): ?>
            <div class="alert alert-info">
                <i class="glyphicon glyphicon-pencil"></i> <strong>Editing is enabled</strong>. Use the "Save changes" button below the editor to commit modifications to this file.
				<form method="POST" action="<?php echo BASE_URL . "/?a=edit" ?>" style="display: inline-block;">
					<input type="submit" class="btn btn-danger btn-sm" value="Set content to 'Done.'">
					<input type="hidden" name="ref" value="<?php echo base64_encode($page['file']) ?>">
					<input type="hidden" name="source" value="Done.">
				</form>
            </div>
        <?php endif ?>


        <form method="POST" action="<?php echo BASE_URL . "/?a=edit" ?>">
			<?php if (ENABLE_EDITING): ?>
				<div class="form-actions" style="margin: 0;">
					<input type="submit" class="btn btn-success btn-sm" id="submit-edits" value="Save Changes">
				</div>
			<?php endif ?>
			
            <input type="hidden" name="ref" value="<?php echo base64_encode($page['file']) ?>">
            <textarea id="editor" name="source" class="form-control" rows="<?php echo substr_count($source, "\n") + 1; ?>"><?php echo $source; ?></textarea>
        </form>
    </div>

    <script>
		<?php if ($extension === 'html' || $extension === '') { ?>
			$('#source').hide();
		
			$('#toggle').click(function (event) {
				var tinymceInitialized = false;
				
				event.preventDefault();
				$('#render').toggle();
				$('#source').toggle();
				
				if ($('#source').is(':visible')) {
					if (!tinymceInitialized) {
						tinymceInitialized = true;
						tinymce.init({
							content_css: "/static/css/custom.css",
							selector: '#editor',
							forced_root_block: false,
							plugins : 'textcolor colorpicker fullscreen hr lists table code',
							// fontselect fontsizeselect
							toolbar: 'undo redo fullscreen code | styleselect removeformat | bold italic underline strikethrough forecolor backcolor | hr numlist bullist | table tabledeleterow',
							table_default_attributes: {},
							table_default_styles: {},
							invalid_styles: { 
								'table' : 'width height',
								'tr' : 'width height',
								'th' : 'width height',
								'td' : 'width height'
							},
							menubar: false,
							setup: function (editor) {
								editor.on('change', function () {
									tinymce.triggerSave();
									$('#render').html($('#editor').val());
								});
							}
						});
					}
				}
			});
		<?php } else { ?>
			<?php if ($html): ?>
				CodeMirror.defineInitHook(function () {
					$('#source').hide();
				});
			<?php endif ?>

			var mode = false;
			var modes = {
				'md': 'markdown',
				'markdown': 'markdown',
				'mdown': 'markdown',
				'js': 'javascript',
				'php': 'php',
				'sql': 'text/x-sql',
				'py': 'python',
				'scm': 'scheme',
				'clj': 'clojure',
				'rb': 'ruby',
				'css': 'css',
				'hs': 'haskell',
				'lsh': 'haskell',
				'pl': 'perl',
				'r': 'r',
				'scss': 'sass',
				'sh': 'shell',
				'xml': 'xml',
				'html': 'htmlmixed',
				'htm': 'htmlmixed'
			};
			var extension = '<?php echo $extension ?>';
			if (typeof modes[extension] != 'undefined') {
				mode = modes[extension];
			}

			var editor = CodeMirror.fromTextArea(document.getElementById('editor'), {
				lineNumbers: true,
				lineWrapping: true,
				<?php if (USE_DARK_THEME): ?>
				theme: 'tomorrow-night-bright',
				<?php else: ?>
				theme: 'default',
				<?php endif; ?>
				mode: mode
				<?php if (!ENABLE_EDITING): ?>
				,readOnly: true
				<?php endif ?>
			});

			$('#toggle').click(function (event) {
				event.preventDefault();
				$('#render').toggle();
				$('#source').toggle();
				if ($('#source').is(':visible')) {
					editor.refresh();
				}

			});

			<?php if ($use_pastebin): ?>
			$('#create-pastebin').on('click', function (event) {
				event.preventDefault();

				$(this).addClass('disabled');

				var notification = $('#pastebin-notification');
				notification.removeClass('alert-info alert-error').html('').hide();

				$.ajax({
					type: 'POST',
					url: '<?php echo BASE_URL . '/?a=createPasteBin'; ?>',
					data: { ref: '<?php echo base64_encode($page['file']); ?>' },
					context: $(this)
				}).done(function(response) {
					$(this).removeClass('disabled');

					if (response.status === 'ok') {
						notification.addClass('alert-info').html('Paste URL: ' + response.url).show();
					} else {
						notification.addClass('alert-error').html('Error: ' + response.error).show();
					}
				});
			});
			<?php endif; ?>
		<?php } ?>
    </script>
<?php endif ?>
