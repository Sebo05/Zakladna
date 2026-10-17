/**
 * @description CookieBar Bronze Systems s.r.o.
 * @author Bronze Systems
 * @version 2.0
  */
class CookieBar
{

	#UI;
	#logged;

	constructor()
	{
		window.cookieBar = this;
		this.#UI = (document.currentScript.src).split('/').slice(0,-1).join('/') + '/' + 'Api/';
		this.#logged = false;

		this.#initConsent();
		this.#updateConsent();

		document.addEventListener('DOMContentLoaded', () => {

			this.#initEvents();

			this.#renderCss('default').then(
				data => this.#message(data)
			).catch(
				error => this.#error(error)
			);

			let operational = new CookieBarStorage('operational');
			let marketing = new CookieBarStorage('marketing');
			let analytic = new CookieBarStorage('analytic');

			if(operational.value === null || marketing.value === null || analytic.value === null) {
				this.#renderCookieBar().then(
					data => this.#message(data)
				).catch(
					error => this.#error(error)
				);
			} else {
				this.#renderButton().then(
					data => this.#message(data)
				).catch(
					error => this.#error(error)
				);
			}

		});
	}

	#initEvents()
	{
		let CookieBar = this;

		// Rozbalování akordeonu řešíme v capture fázi a cílový stav vynutíme až po doběhnutí
		// ostatních posluchačů. Některé weby mají v theme JS vlastní jQuery handler, který dělá
		// to samé (toggleClass('active')); dva toggly na jeden klik se vyruší a lišta se pak
		// tváří, že na ni nejde kliknout. Takhle je jedno, kolik dalších přepínačů tam visí.
		document.addEventListener('click', function(event){
			let toggle = event.target.closest('[data-cookie-bar-accordion-toggle]');
			if(!toggle) {
				return;
			}

			let accordion = toggle.closest('[data-cookie-bar-accordion]');
			if(!accordion) {
				return;
			}

			let target = !accordion.classList.contains('active');
			setTimeout(() => accordion.classList.toggle('active', target), 0);
		}, true);

		document.addEventListener('click', function(event){
			let setup = event.target.closest('[data-cookie-bar-setup]');
			if(setup) {
				event.preventDefault();
				let type = setup.getAttribute('data-cookie-bar-setup');
				let inputs = document.querySelectorAll('input[data-cookie-bar-input]');
				inputs.forEach(el => {
					let name = el.getAttribute('data-cookie-bar-input');
					let value = type === 'enable' || (type === 'select' && el.checked) ? 'enable' : 'disable';
					let cookieStorage = new CookieBarStorage(name);
					cookieStorage.value = value;
					cookieStorage.expires = 60 * 60 * 24 * 365;
					cookieStorage.save();
				});
				CookieBar.#updateConsent();
				CookieBar.#redraw();
				CookieBar.#renderButton().then(
					data => CookieBar.#message(data)
				).catch(
					error => CookieBar.#error(error)
				);
			}

			let addGroup = event.target.closest('[data-cookie-bar-settings-add-group]');
			if(addGroup) {
				event.preventDefault();

				let xhttpParams = {
					'action': 'html',
					'data': 'terminalGroup'
				};

				CookieBar.#xhttp(xhttpParams).then(
					data => {
						let groupTemplate = data.content;
						let group = {'name': '------', 'url': '', 'index': Math.floor(Math.random() * 1000)};
						let groupHtml = CookieBar.#interpolate(groupTemplate, group);
						addGroup.insertAdjacentHTML('afterend', groupHtml);
						CookieBar.#renderImages();
					}
				).catch(
					error => CookieBar.#error(error)
				);
			}

			let addItem = event.target.closest('[data-cookie-bar-settings-add-item]');
			if(addItem) {
				event.preventDefault();

				let xhttpParams = {
					'action': 'html',
					'data': 'terminalItem'
				};

				CookieBar.#xhttp(xhttpParams).then(
					data => {
						let itemTemplate = data.content;
						let item = {'name': '------', 'description': '', 'expire': '', 'type': '', 'group': {'index': Math.floor(Math.random() * 1000)}, 'index': Math.floor(Math.random() * 1000)};
						let itemHtml = CookieBar.#interpolate(itemTemplate, item);
						addItem.insertAdjacentHTML('afterend', itemHtml);
						CookieBar.#renderImages();
					}
				).catch(
					error => CookieBar.#error(error)
				);
			}

			let removeGroup = event.target.closest('[data-cookie-bar-settings-remove-group]');
			if(removeGroup) {
				event.preventDefault();
				removeGroup.closest('[data-cookie-bar-settings-group]').remove();
			}

			let removeItem = event.target.closest('[data-cookie-bar-settings-remove-item]');
			if(removeItem) {
				event.preventDefault();
				removeItem.closest('[data-cookie-bar-settings-item]').remove();
			}
		});

		document.addEventListener('submit', function(event){
			if(event.target.hasAttribute('data-cookie-bar-login')) {
				event.preventDefault();
				let formData = new FormData(event.target);
				CookieBar.#getLogin(formData.get('username'), formData.get('password')).then(
					data => {
						CookieBar.#logged = true;
						CookieBar.#message(data);
						CookieBar.#renderTerminal();
					}
				).catch(
					error => CookieBar.#error(error)
				);
			}

			if(event.target.hasAttribute('data-cookie-bar-terminal')) {
				event.preventDefault();
				let formData = new FormData(event.target);
				CookieBar.#setSettings(formData).then(
					data => CookieBar.#message(data)
				).catch(
					error => CookieBar.#error(error)
				);
			}

			if(event.target.hasAttribute('data-cookie-bar-button')) {
				event.preventDefault();
				CookieBar.#renderCookieBar();

			}
		});
	}

	/**
	 * @param {object} params
	 */
	#xhttp(params)
	{
		return new Promise((resolve, reject) => {
			let xhttp = new XMLHttpRequest();
			xhttp.open('POST', this.#UI, true);
			xhttp.setRequestHeader('Content-type', 'application/json');
			xhttp.ontimeout = () => reject('Request timed out');
			xhttp.onerror = () => reject('Request error: ' + xhttp.status);
			xhttp.onload = () => {
				if(xhttp.readyState === 4) {
					if (xhttp.status === 200) {
						try {
							resolve(JSON.parse(xhttp.responseText));
						} catch(error) {
							reject('Response error: ' + error);
						}
					} else {
						reject('Response error: ' + xhttp.status);
					}
				}
			};
			xhttp.send(JSON.stringify(params));
		});
	}

	/**
	 * @param {string} message
	 */
	#error(message)
	{
		console.error('%cCookie Bar%c' + message, 'background: #f0506e; color: #fff');
	}

	/**
	 * @param {string} message
	 */
	#message(message)
	{
		console.info('%cCookie Bar%c' + message, 'background: #41ba55; color: #fff');
	}

	/**
	 *
	 * @param {string} template
	 * @param {object} object
	 */
	#interpolate(template, object)
	{
		return (new Function(...Object.keys(object), 'return `' + template + '`;'))(...Object.values(object));
	}

	/**
	 * @param {FormData} formData
	 */
	#setSettings(formData)
	{
		let data = [];
		let groupIndex = 0;
		let itemIndex = 0;
		for(let [key, value] of formData.entries()) {
			if(key === 'sklik') {
				if(value !== '') {
					groupIndex = data.length;
					data[groupIndex] = {'sklik': value};
				}
				continue;
			}

			let [,name,,isItem] = key.match(/([^\]]*)\[(\d+)?\](\[(\d+)?\])?/);

			if(name === 'name' && isItem === undefined) {
				groupIndex = data.length;
				itemIndex = 0;
			}

			data[groupIndex] = data[groupIndex] || {};

			if(name === 'name' && isItem !== undefined) {
				data[groupIndex].items = data[groupIndex].items || [];
				itemIndex = data[groupIndex].items.length
			}

			if(isItem !== undefined) {
				data[groupIndex].items[itemIndex] = data[groupIndex].items[itemIndex] || {};
				data[groupIndex].items[itemIndex][name] = value;
			} else {
				data[groupIndex][name] = value;
			}
		}

		return new Promise((resolve, reject) => {
			let xhttpParams = {
				'action': 'settings',
				'data': JSON.stringify(data)
			};

			this.#xhttp(xhttpParams).then(
				() => resolve('Settings saved')
			).catch(
				error => reject(error)
			);
		});
	}

	#setDataLayer()
	{
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push(arguments);
	}

	/**
	 * @param {bool} consent
	 */
	#setSklik(consent)
	{
		this.#getSettings().then(groups => {
			groups.forEach((group) => {
				if(typeof group['sklik'] === 'undefined') {
					return;
				}

				if(typeof window.rc === 'undefined' || typeof window.sznIVA === 'undefined' || typeof window.sznIVA.IS === 'undefined') {
					this.#error('Missing implements Sklik retargeting code.');
					return;
				}

				window.sznIVA.IS.updateIdentities({
					eid: null
				});

				window.rc.retargetingHit({
					rtgId: group['sklik'],
					consent: consent ? 1 : 0,
				});
			});
		});
	}

	#initConsent()
	{
		this.#setDataLayer(
			'consent',
			'default',
			{
				'functionality_storage': 'denied',
				'security_storage': 'denied',
				'personalization_storage': 'denied',
				'ad_storage': 'denied',
				'analytics_storage': 'denied',
				'ad_user_data': 'denied',
				'ad_personalization': 'denied',
				'wait_for_update': 2000,
			}
		);

		this.#setSklik(false);
	}

	#updateConsent()
	{
		let operational = new CookieBarStorage('operational');
		let marketing = new CookieBarStorage('marketing');
		let analytic = new CookieBarStorage('analytic');

		this.#setDataLayer(
			'consent',
			'update',
			{
				'functionality_storage': 'granted',
				'security_storage': 'granted',
				'personalization_storage': operational.value === 'enable' ? 'granted' : 'denied',
				'ad_storage': marketing.value === 'enable' ? 'granted' : 'denied',
				'ad_personalization': marketing.value === 'enable' ? 'granted' : 'denied',
				'ad_user_data': marketing.value === 'enable' ? 'granted' : 'denied',
				'analytics_storage': analytic.value === 'enable' ? 'granted' : 'denied',
			}
		);

		this.#setSklik(marketing.value === 'enable');
	}

	#getSettings()
	{
		return new Promise((resolve, reject) => {
			let xhttpParams = {
				'action': 'settings',
			};

			this.#xhttp(xhttpParams).then(
				data => resolve(data)
			).catch(
				error => reject(error)
			);
		});
	}

	/**
	 * @param {string} username
	 * @param {string} password
	 */
	#getLogin(username, password)
	{
		return new Promise((resolve, reject) => {
			let xhttpParams = {
				'action': 'login',
				'data': username + ':' + password
			};

			this.#xhttp(xhttpParams).then(
				data => resolve('Logged in')
			).catch(
				error => reject(error)
			);
		});
	}

	/**
	 * @param {string} filename
	 */
	#getImage(filename)
	{
		return new Promise((resolve, reject) => {
			let xhttpParams = {
				'action': 'image',
				'data': filename
			};

			this.#xhttp(xhttpParams).then(
				data => resolve(data)
			).catch(
				error => reject(error)
			);
		});
	}

	#renderlogin()
	{
		return new Promise((resolve, reject) => {
			let xhttpParams = {
				'action': 'html',
				'data': 'login'
			};

			this.#renderCss('terminal').then(
				data => this.#message(data)
			).catch(
				error => this.#error(error)
			);

			this.#xhttp(xhttpParams).then(
				data => {
					this.#redraw();
					document.body.insertAdjacentHTML('beforeend', data.content);
					this.#renderImages();
					resolve('Login loaded');
				}
			).catch(
				error => reject(error)
			);
		});
	}

	/**
	 * @param {string} filename
	 */
	#renderCss(filename)
	{
		return new Promise((resolve, reject) => {
			let xhttpParams = {
				'action': 'css',
				'data': filename,
			};

			this.#xhttp(xhttpParams).then(
				data => {
					let style = document.createElement('style');
					style.textContent = data.content;
					document.head.insertAdjacentElement('beforeend', style);
					resolve('CSS loaded')
				}
			).catch(
				error => reject(error)
			);
		});
	}

	#renderImages()
	{
		let images = document.querySelectorAll('[data-cookie-bar-image]');
		images.forEach(el => {
			this.#getImage( el.getAttribute('data-cookie-bar-image') ).then(
				data => {
					el.src = data.content;
					el.removeAttribute('data-cookie-bar-image');
					this.#message('Image loaded');
				}
			);
		});
	}

	#renderButton()
	{
		return new Promise((resolve, reject) => {
			let xhttpParams = {
				'action': 'html',
				'data': 'button'
			};

			this.#xhttp(xhttpParams).then(
				data => {
					this.#redraw();
					document.body.insertAdjacentHTML('beforeend', data.content);
					this.#renderImages();
					resolve('Button loaded');
				}
			).catch(
				error => reject( error )
			);
		});
	}

	#renderCookieBar()
	{
		return new Promise((resolve, reject) => {
			let xhttpParams = {
				'action': 'html',
				'data': 'default'
			};

			this.#xhttp(xhttpParams).then(
				data => {
					this.#redraw();
					document.body.insertAdjacentHTML('beforeend', data.content);

					let checkboxes = document.querySelectorAll('[data-cookie-bar-input]');
					checkboxes.forEach(el => {
						let name = el.getAttribute('data-cookie-bar-input');
						let cookieStorage = new CookieBarStorage(name);
						if(cookieStorage.value === 'enable') {
							el.setAttribute('checked', '');
						}
					});

					let xhttpParams = {
						'action': 'html',
						'data': 'defaultGroup'
					};
					this.#xhttp(xhttpParams).then(
						data => {
							let groupTemplate = data.content;

							let xhttpParams = {
								'action': 'html',
								'data': 'defaultItem'
							};

							this.#xhttp(xhttpParams).then(
								data => {
									let itemTemplate = data.content;

									this.#getSettings().then(
										groups => {
											let divs = document.querySelectorAll('[data-cookie-bar-cookies-group]');
											divs.forEach(el => {
												let type = el.getAttribute('data-cookie-bar-cookies-group');
												let divCount = document.querySelector('[data-cookie-bar-cookies-group-count="' + type + '"]');
												let groupCount = 0;

												groups.forEach((group) => {
													if(typeof group[type] === 'undefined') {
														return;
													}
													groupCount++;

													group.renderItems = '';
													if(group.items !== undefined) {
														group.items.forEach(item => {
															let itemHtml = this.#interpolate(itemTemplate, item);
															group.renderItems += ' ' + itemHtml;
														});
													}

													let groupHtml = this.#interpolate(groupTemplate, group);
													el.insertAdjacentHTML('beforeend', groupHtml);
												});
												divCount.textContent = groupCount ? ' - ' + groupCount : ''
											});

											this.#renderImages();
										}
									).catch(
										error => this.#error(error)
									);
								}
							).catch(
								error => this.#error(error)
							);
						}
					).catch(
						error => this.#error(error)
					);
					this.#renderImages();
					resolve('Default loaded');
				}
			).catch(
				error => reject( error )
			);
		});
	}



	#renderSettings()
	{
		let div = document.querySelector('[data-cookie-bar-settings]');

		let xhttpParams = {
			'action': 'html',
			'data': 'terminalGroup'
		};

		this.#xhttp(xhttpParams).then(
			data => {
				let groupTemplate = data.content;

				let xhttpParams = {
					'action': 'html',
					'data': 'terminalItem'
				};

				this.#xhttp(xhttpParams).then(
					data => {
						let itemTemplate = data.content;

						this.#getSettings().then(
							groups => {
								groups.forEach((group, groupIndex) => {
									if(typeof group['sklik'] !== 'undefined') {
										document.querySelector('input[name="sklik"]').value = group['sklik'];
										return;
									}

									group.index = groupIndex;
									let groupHtml = this.#interpolate(groupTemplate, group);
									div.insertAdjacentHTML('beforeend', groupHtml);

									if(group.items === undefined) {
										return;
									}

									group.items.forEach((item, itemIndex) => {
										item.group = group;
										item.index = itemIndex;
										let groupItemHtml = this.#interpolate(itemTemplate, item);
										div.querySelector('[data-cookie-bar-settings-group-items]').insertAdjacentHTML('beforeend', groupItemHtml);
									});

									this.#renderImages();
								});
							}
						).catch(
							error => this.#error(error)
						);
					}
				).catch(
					error => this.#error(error)
				);
			}
		).catch(
			error => this.#error(error)
		);
	}

	#renderTerminal()
	{
		return new Promise((resolve, reject) => {
			let xhttpParams = {
				'action': 'html',
				'data': 'terminal'
			};

			this.#xhttp(xhttpParams).then(data => {
				this.#redraw();
				document.body.insertAdjacentHTML('beforeend', data.content);
				this.#renderSettings();

				this.#renderImages();
				resolve('Terminal loaded');
			})
			.catch(
				error => reject(error)
			);
		});
	}

	#redraw()
	{
		let elements = document.querySelectorAll('[data-cookie-bar-modal],[data-cookie-bar-button]');
		elements.forEach(element => {
			element.remove();
		});
	}

	get terminal()
	{
		if(!this.#logged) {
			this.#renderlogin().then(
				data => this.#message(data)
			).catch(
				error => this.#error(error)
			);
		} else {
			this.#renderTerminal().then(
				data => this.#message(data)
			).catch(
				error => this.#error(error)
			);
		}

		return null;
	}

}

class CookieBarStorage
{

	/**
	 * @type {string}
	 */
	#name;

	/**
	 * @type {int}
	 */
	#expires = 0;

	/**
	 * @type {string}
	 */
	#prefix;

	/**
	 * @param {string} name
	 */
	constructor(name)
	{
		this.#prefix = `_${this.constructor.name}_`;
		this.#name = name;
		let match = document.cookie.match(RegExp(this.#prefix + this.#name + '=([^;]*)'));
		this.value = match ? decodeURIComponent(match[1]) : null;
	}

	/**
	 * @param {int} seconds
	 */
	set expires(seconds)
	{
		this.#expires = parseInt(seconds);
	}

	save()
	{
		let date = new Date();
		date.setTime(date.getTime() + this.#expires * 1000);

		let name = this.#prefix + this.#name;
		let value = encodeURIComponent(this.value);
		let expires = date.toUTCString();

		document.cookie = `${name}=${value}; expires=${expires}; path=/`;
	}

}

new CookieBar();