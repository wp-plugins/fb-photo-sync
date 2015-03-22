(function($) {
	window.fbps = {

		init: function() {
			this.events();
		},

		events: function() {
			var self = this;

			$('.fbps-list').on('change', 'input[type=checkbox]', function() {
				var checked = $(this).prop('checked');
				if(checked) {
					var checkbox = $(this).parents('li').clone();
					$('#import-form ul').prepend(checkbox);
				} else {
					$('#import-form').find('li[data-id='+$(this).val()+']').remove();
				}
			});
			
			$('#fbps-page-input').keypress(function(e) {
				if(e.keyCode === 13) { // pressed return
					$('#fbps-load-albums').trigger('click');
				}
			});
			
			$('#import-form').on('change', 'input[type=checkbox]', function() {
				var checked = $(this).prop('checked');
				if(!checked) {
					$(this).parents('li').remove();
					$('li[data-id='+$(this).val()+'] input[type=checkbox]').prop('checked', false);
				}
			});

			$('#import-form').on('click', '#import-button', function(e) {
				e.preventDefault();
				$('#import-form ul li').each(function() {
					$(this).append('<span class="spinner" />');
					$(this).find('.spinner').show();
					var album_id = $(this).find('input[type=checkbox]').val();
					self.facebook_import(album_id, $(this), $('#fbps-wp-images').prop('checked'));
				});
			});

			$('.fbps-options code').zclip({
				path: '/wp-content/plugins/fb-photo-sync/js/ZeroClipboard.swf',
				copy: function() {
					return $(this).text();
				}
			});

			$('.fbps-options code').click(function() {
				$(this).next('small').remove();
				$(this).after(' <small>copied!</small>');
				var $copied = $(this).next('small');
				setTimeout(function() {
					$copied.fadeOut(500, function() {
						$(this).remove();
					});
				}, 2000);
			});

			$('#fbps-album-list').on('click', '.delete-album', function(e) {
				e.preventDefault();
				var title = $(this).parents('li').find('h3').text();
				if(confirm('Are you sure you want to permanently delete "'+title+'"?')) {
					var data = {
						action: 'fbps_delete_album',
						nonce: $('#nonce').val(),
						id: $(this).parents('li').data().id
					};
					$.post(ajaxurl, data, function(r) {
						if(r.success) {
							$('li[data-id='+r.data.id+']').fadeOut(500, function() {
								$(this).remove();
							});
						}
					});
				}
			});

			$('#fbps-album-list').on('click', '.sync-album', function(e) {
				e.preventDefault();
				var $this = $(this);
				$this.parents('.fbps-options').find('p:first-child').append('<span style="float: left" class="spinner" />');
				$this.parents('li').find('.spinner').show();
				var album_id = $this.parents('li').data().id;
				self.facebook_import(album_id, $this.parents('li'), $this.parents('.fbps-options').find('.fbps-wp-photos').prop('checked'));
			});
			
			$(document.body).on('click', '#fbps-load-albums', function(e) {
				e.preventDefault();
				var page_id = $('#fbps-page-input').val();
				FB.api('/'+page_id, {fields: 'albums'}, function(r) {
					if(r.albums) {
						self.albums = [];
						self.get_albums(r.albums);
					}
				});
			});
		},

		get_albums: function(albums) {
			var self = this,
				album_list = '',
				count = '',
				description = '';

			self.albums = self.albums.concat(albums.data);

			if (self.test_obj(albums, 'paging.next')) {
				FB.api(albums.paging.next, function(r) {
					self.get_albums(r);
				});
			} else {
				var albums = self.albums;
				for(var i = 0; i < albums.length; i++) {
					var album = albums[i];
					if (album.count) {
						count = ' (<span class="fbps-counter"><span>0</span> of </span>'+album.count+' photos)';
					}
					if (album.description) {
						description = album.description;
					}
					album_list += '<li data-id="'+album.id+'" title="'+description+'"><label><input type="checkbox" value="'+album.id+'" /> '+album.name+count+'</li>';
				}
				$('#fbps-page-album-list').html(album_list);
			}
		},

		facebook_import: function(album_id, $parent, wp_photos) {
			var self = this;
			$parent.find('.fbps-counter').show();
			FB.api('/'+album_id, {fields: 'photos,picture,name'}, function(r) {
				var album = {
					items: []
				};
				if(r.photos) {
					album.id = r.id;
					album.name = r.name;
					if(self.test_obj(r, 'picture.data.url')) {
						album.picture = r.picture.data.url
					}
					var items = r.photos.data;
					for(var i = 0; i < items.length; i++) {
						var item = {
							id: items[i].id,
							photos: items[i].images,
							link: items[i].link,
							name: items[i].name,
							picture: items[i].picture,
							show: true
						};
						album.items.push(item);
					}
					self.ajax_save(r, album, $parent, wp_photos);
				} else {
					$parent.find('.fbps-counter').hide();
				}
			});
		},

		items_paging: function(url, album, $parent, wp_photos) {
			var self = this;
			$.getJSON(url, function(r) {
				if(r) {
					var items = r.data;
					for(var i = 0; i < items.length; i++) {
						var item = {
							id: items[i].id,
							photos: items[i].images,
							link: items[i].link,
							name: items[i].name,
							picture: items[i].picture,
							show: true
						};
						album.items.push(item);
					}
				}
				self.ajax_save(r, album, $parent, wp_photos);
			});
		},

		ajax_save: function(r, album, $parent, wp_photos) {
			var self = this,
				album_str = JSON.stringify(album),
				data = {
					action: 'fbps_save_album',
					nonce: $('#nonce').val(),
					album: album_str,
					wp_photos: wp_photos
				};
			
			$.post(ajaxurl, data, function(d) {
				if(d.error) {
					alert('There was an issue with this album.');
				}
				$parent.find('.fbps-counter span').html(album.items.length);
				if (self.test_obj(r, 'photos.paging.next')) {
					self.items_paging(r.photos.paging.next, album, $parent, wp_photos);
				} else if (self.test_obj(r, 'paging.next')) {
					self.items_paging(r.paging.next, album, $parent, wp_photos);
				} else {
					$parent.find('.spinner').remove();
					$parent.find('.fbps-counter').hide();
					$parent.find('.fbps-counter span').html('0');
				}
			});
		},

		test_obj: function(obj, prop) {
			var parts = prop.split('.');
			for(var i = 0, l = parts.length; i < l; i++) {
					var part = parts[i];
					if(obj !== null && typeof obj === "object" && part in obj) {
							obj = obj[part];
					}
					else {
							return false;
					}
			}
			return true;
		}

	};
	window.fbps.init();
})(jQuery);
