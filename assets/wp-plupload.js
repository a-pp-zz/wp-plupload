(function ($) {

	$(function () {
		
		var uploadedFiles = []

		function pluploadError(fileObj, errorCode, message, uploader) {

			var translated = null

			switch (errorCode) {
				case plupload.FAILED:
					translated = pluploadConfig.upload_failed
					break;
				case plupload.FILE_EXTENSION_ERROR:
					translated = 'Неверный тип файла, выберите другой!';
					//pluploadConfig.invalid_filetype
					break;
				case plupload.FILE_SIZE_ERROR:
					translated = pluploadConfig.file_exceeds_size_limit.replace('%s', fileObj.name)
					//translated = translated)
					break;
				case plupload.IMAGE_FORMAT_ERROR:
					translated = pluploadConfig.not_an_image
					break;
				case plupload.IMAGE_MEMORY_ERROR:
					translated = pluploadConfig.image_memory_exceeded
					break;
				case plupload.IMAGE_DIMENSIONS_ERROR:
					translated = pluploadConfig.image_dimensions_exceeded
					break;
				case plupload.GENERIC_ERROR:
					translated = pluploadConfig.upload_failed
					break;
				case plupload.IO_ERROR:
					translated = pluploadConfig.io_error
					break;
				case plupload.HTTP_ERROR:
					translated = pluploadConfig.http_error;
					break;
				case plupload.SECURITY_ERROR:
					translated = pluploadConfig.security_error
					break;
				default:
					translated = pluploadConfig.default_error
			}

			return translated
		}

		var showError = function (message, file, filelist_sel) {
			 var media_id = '#media-item-o_' + file.id
			 message = '<strong>«'+file.name+'»</strong><br />' + message		
			 var html = '<div class="plupload-error"><a class="dismiss" href="#">Закрыть</a>'+message+'</div>'	      
			 
			 if ($(media_id).length > 0)
			 	$(media_id).html(html)
			 else
			 	$(filelist_sel).append('<div class="media-item child-of-0" id="media-item-o_'+file.id+'">' + html + '</div>')
		}	

		var showMessage = function (file, message, filelist_sel, status) {
			 
		 	 var media_id = '#media-item-o_' + file.id
		 	 var messageFull = '<strong>'+file.name+'</strong>';			 			 
			 var showDismiss = false;
			 var showProgress = false;

			 if (message) {
			 	messageFull += '<br />' + message			
			 }

			 switch (status) {
			 	case 'new':
			 		className = 'plupload-new'
			 		showDismiss = false
			 		showProgress = true
			 		messageFull = '<div class="filename original">'+file.name+'</div>'
			 	break
			 	case 'error':
			 		className = 'plupload-error'
			 		showDismiss = true
			 		showProgress = false		 		
			 	break
			 	case 'success':
			 		className = 'plupload-success'
			 		showDismiss = true
			 		showProgress = false		 		
			 	break	
			 	case 'finish':
			 		className = 'plupload-finish'
			 		showDismiss = false
			 		showProgress = false	
			 		messageFull = 'Все файлы успешно загружены!'	 		
			 	break			 		 			 		
			 }

			 var html = '<div class="plupload-status '+className+'">'

			 if (showDismiss) {
			 	html += '<a class="dismiss" href="#">Закрыть</a>'
			 }

			 if (showProgress) {
			 	html += '<div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div>'
			 }

			 html += messageFull + '</div>'	

			 if ($(media_id).length > 0) {
			 	$(media_id).html(html)
			 }		 	
			 else {
			 	$(filelist_sel).append('<div class="media-item child-of-0" id="media-item-o_'+file.id+'">' + html + '</div>')
			 }	
		}		

		var pluploadInit = function (container) {
			var data = $(container).data()

			var params = {
				action: 'plupload',
				_wpnonce: data.nonce
			}

			var multipart_params = {
				newname: data.name,
				types: data.types,
				dir: data.dir,
				ow: data.ow
			}

			var browse_button = $(container).find('.plupload-pickfiles');
			var max_file_size_sel = $(container).find('.plupload-max-file-size');
			var allowed_formats_sel = $(container).find('.plupload-allowed-formats');
			var filelist_sel = $(container).find('.plupload-filelist');	
			var preview_sel = $(container).find('.plupload-preview');	
			var max_files_count = data.multi ? Number (data.multi) : 1;
			
			var receiver = {}

			if (data.receiver) {
				var receiver_obj = $(data.receiver)
				
				if ($(receiver_obj).length > 0) {
					receiver.tag = receiver_obj.prop("tagName").toLowerCase()		
					receiver.obj = receiver_obj
					receiver.preview = preview_sel

					if (receiver.preview && data.previewWidth) {
						receiver.preview_width = data.previewWidth;
					}

				} else {
					receiver = null
				}
			}
					
			var uploader = new plupload.Uploader({
				runtimes : 'html5,html4',
				browse_button : $(browse_button).attr('id'),
				container : $(container).attr('id'),
				max_file_size : data.maxsize,
				url : pluploadConfig.ajaxurl + '?' + $.param(params),
				file_data_name: data.filefield,
				multi_selection: data.multi ? true : false,
				multipart_params : multipart_params,
				filters: {
				  mime_types : [
				  	{title : 'Разрешенные типы', extensions: data.types}
				  ]
				},
			});			

			uploader.bind('Init', function(up, params) {
				$(max_file_size_sel).html(data.maxsize);
				$(allowed_formats_sel).html(data.types.replace(',',', '));

				if (max_files_count) {
					$(container).find('.plupload-features').append('<div>Максимальное число загружаемых файлов: '+max_files_count+'</div>')
				}

				uploader.refresh();
			});
		  
			uploader.bind('FilesAdded', function(up, files) {

					if (files.length > max_files_count) {
						alert ('Максимальное число загружаемых файлов: '+max_files_count);
						up.splice(0);
						up.refresh();
						return false;
					}

			  	$(container).removeClass('is-uploaded')
			  	$(filelist_sel).html('')

				if (receiver) {
					if (receiver.tag == 'input' || receiver.tag == 'textarea') {
						receiver.obj.val('')
					} else {
						receiver.obj.html('');
					}

					if (receiver.preview) {
						receiver.preview.hide();
					}
				}		  

			  $.each(files, function(i, file) {
			  	  showMessage (file, null, $(filelist_sel), 'new')
			  });

			  up.refresh();
			  uploader.start();
			});		

			/*
			uploader.bind('QueueChanged', function(up) {
			  console.log(up.files)
			});
			*/

			uploader.bind('UploadProgress', function(up, file) {
			  var media_id = '#media-item-o_' + file.id
			  $(media_id).find('.plupload-remove-file').hide()
			  $(media_id).find('.progress').show();
			  $(media_id).find('.progress .percent').html(file.percent + '%')
			  $(media_id).find('.progress .bar').css('width', file.percent*2)		  
			});

			uploader.bind('Error', function(up, err) {
				$(container).removeClass('is-uploaded')
			 	var message = pluploadError (err.file, err.code, err.message, up)
			 	showMessage (err.file, message, $(filelist_sel), 'error')
			 	up.refresh();
				
				if (receiver) {
					if (receiver.tag == 'input' || receiver.tag == 'textarea') {
						receiver.obj.val('')
					} else {
						receiver.obj.html('');
					}

					if (receiver.preview) {
						receiver.preview.hide();
					}				
				}	
			});

			$(".plupload-filelist").on('click', '.media-item a.dismiss', function() {
				$(this).parent().parent().remove()
				return false
			});	

			uploader.bind('UploadComplete', function(up, files) {
				$(container).addClass('is-uploaded')
				if (receiver) {
					if (receiver.tag == 'input' || receiver.tag == 'textarea') {
						if (uploadedFiles.length === 0) {
							receiver.obj.val('')
						} else {
							var json = JSON.stringify(uploadedFiles)
							receiver.obj.val(json)		
						}
					}
				}
				uploadedFiles = []
			})

			uploader.bind('FileUploaded', function(up, file, info) {

				var response = $.parseJSON (info.response);			

				if (response.status === 201) {
					uploadedFiles.push(response.filename)
					showMessage (file, 'Файл успешно загружен!', $(filelist_sel), 'success')
					
					if (receiver.preview && response.mime.search('image') !== -1) {
						var img = '<img width="'+receiver.preview_width+'" src="'+response.url+'" />'
						receiver.preview.html(img).show();
					}								
				} else {
					showMessage (file, response.message, $(filelist_sel), 'error')
				}
			}); 
	 
			uploader.init();		
		}

		$('.wp-plupload-container').each(function() {
			pluploadInit($(this))
		})

    $(window).on('wp-plupload-reinit', function (event, target) {
    	pluploadInit(target)  
    });	

    $(window).on('wp-plupload-preview', function (event, target) {
			var preview = $(target.target).find('.plupload-preview'),
					previewWidth = $(target.target).data('preview-width'),
					receiver = $(target.target).data('receiver');
					description = $(target.target).find('.plupload-filedesc textarea'),					
					img = '<img width="'+previewWidth+'" src="'+target.preview.file+'" />';			
			preview.html(img).show();
			$(receiver).val(JSON.stringify([target.preview.path]));
			$(description).val(target.preview.description);
    });    

	})

})(jQuery)
