/*!
 * Rocket Cloud embed: the file picker.
 *
 *   <script src="https://cloud.example.com/embed.js"></script>
 *   <script>
 *     const picker = RocketCloud.mount('#picker', {
 *       baseUrl: 'https://cloud.example.com',
 *       applicationId: '<application uuid>',
 *       // Called whenever a token is needed (initially and when it expires).
 *       // It must hit YOUR backend, which calls POST /api/embed/token with the application secret.
 *       getToken: () => fetch('/rocket-cloud/token').then(r => r.json()).then(d => d.token),
 *       mode: 'file',   // or 'folder' (choose where to save)
 *       accept: ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],  // optional
 *       onSelect: selection => console.log(selection.file ?? selection.folder),
 *       onCancel: () => {},
 *     })
 *     picker.refresh()
 *     picker.destroy()
 *   </script>
 *
 * Or, as a web component (same script):
 *
 *   <rocket-cloud-picker application-id="<application uuid>" token-url="/rocket-cloud/token" accept=".docx"></rocket-cloud-picker>
 *   <script>
 *     document.querySelector('rocket-cloud-picker').addEventListener('select', event => console.log(event.detail.file))
 *   </script>
 *
 * The application secret must never be sent to the browser: only short-lived embed tokens are. The host then reads
 * the chosen file server-side (GET /api/files/{id}/content with its application token and X-Impersonate-User).
 */
(function (global) {
  'use strict'

  var SOURCE = 'rocket-cloud'
  // Where this script is served from: the default Rocket Cloud URL of the web component.
  var SCRIPT_ORIGIN = (function () {
    try { return new URL(document.currentScript.src).origin } catch (e) { return null }
  })()
  // File extensions accepted by the picker, as MIME types (the picker filters on the type detected by Rocket Cloud).
  var EXTENSIONS = {
    '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    '.pptx': 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    '.pdf': 'application/pdf',
    '.odt': 'application/vnd.oasis.opendocument.text',
    '.txt': 'text/plain',
    '.csv': 'text/csv',
  }

  function acceptList(accept) {
    if (!accept) return []
    var list = Array.isArray(accept) ? accept : String(accept).split(',')
    return list.map(function (item) {
      item = String(item).trim()
      return EXTENSIONS[item.toLowerCase()] || item
    }).filter(Boolean)
  }

  function mount(target, options) {
    var container = typeof target === 'string' ? document.querySelector(target) : target
    if (!container) throw new Error('RocketCloud: container not found')
    if (!options || !options.baseUrl || !options.applicationId || typeof options.getToken !== 'function') {
      throw new Error('RocketCloud: baseUrl, applicationId and getToken are required')
    }

    var origin = new URL(options.baseUrl).origin
    var query = '?app=' + encodeURIComponent(options.applicationId)
    if (options.mode === 'folder') query += '&mode=folder'
    var accept = acceptList(options.accept)
    if (accept.length) query += '&accept=' + encodeURIComponent(accept.join(','))
    if (options.folder) query += '&folder=' + encodeURIComponent(options.folder)

    var iframe = document.createElement('iframe')
    iframe.src = origin + '/embed/picker' + query
    iframe.title = options.title || 'Rocket Cloud'
    iframe.style.width = '100%'
    iframe.style.border = '0'
    iframe.style.minHeight = (options.minHeight || 360) + 'px'
    iframe.setAttribute('referrerpolicy', 'strict-origin')
    container.appendChild(iframe)

    function post(message) {
      message.source = SOURCE
      // Always target the Rocket Cloud origin so the token cannot leak to another document.
      iframe.contentWindow.postMessage(message, origin)
    }

    function sendToken() {
      return Promise.resolve(options.getToken()).then(function (token) {
        post({ type: 'token', token: token })
      }).catch(function (error) {
        if (options.onError) options.onError(error)
      })
    }

    function onMessage(event) {
      if (event.origin !== origin || event.source !== iframe.contentWindow) return
      var data = event.data || {}
      if (data.source !== SOURCE) return

      switch (data.type) {
        case 'ready':
        case 'token-request':
          sendToken()
          break
        case 'loaded':
          if (options.onReady) options.onReady()
          break
        case 'resize':
          if (options.autoResize !== false && data.height) iframe.style.height = data.height + 'px'
          break
        case 'selected':
          if (options.onSelect) options.onSelect(data.file ? { file: data.file } : { folder: data.folder })
          break
        case 'cancel':
          if (options.onCancel) options.onCancel()
          break
      }
    }

    global.addEventListener('message', onMessage)

    return {
      iframe: iframe,
      refresh: function () { post({ type: 'refresh' }) },
      destroy: function () {
        global.removeEventListener('message', onMessage)
        iframe.remove()
      },
    }
  }

  global.RocketCloud = { mount: mount }

  /*
   * <rocket-cloud-picker> web component.
   *
   * Attributes: application-id (required), token-url (or the getToken property), token-method (POST),
   * base-url (default: where embed.js is served from), mode (file | folder), accept (MIME types, prefixes or
   * extensions, comma-separated), folder (id to open), min-height, no-auto-resize, frame-title.
   * Properties: getToken, iframe (read-only). Method: refresh().
   * Events (bubbling, composed): "ready", "select" (detail: {file} or {folder}), "cancel", "error" (detail: the error).
   */
  if (!global.customElements || global.customElements.get('rocket-cloud-picker')) return

  var OBSERVED = ['application-id', 'base-url', 'token-url', 'token-method', 'mode', 'accept', 'folder', 'min-height', 'no-auto-resize', 'frame-title']

  function RocketCloudPicker() {
    return Reflect.construct(HTMLElement, [], RocketCloudPicker)
  }
  RocketCloudPicker.prototype = Object.create(HTMLElement.prototype)
  RocketCloudPicker.prototype.constructor = RocketCloudPicker
  Object.setPrototypeOf(RocketCloudPicker, HTMLElement)
  Object.defineProperty(RocketCloudPicker, 'observedAttributes', { get: function () { return OBSERVED } })

  var proto = RocketCloudPicker.prototype

  proto._init = function () {
    if (this._root) return
    this._root = this.attachShadow({ mode: 'open' })
    this._root.innerHTML = '<style>:host{display:block}:host([hidden]){display:none}div{width:100%}</style><div part="container"></div>'
    this._container = this._root.querySelector('div')
    this._instance = null
    // A property set before embed.js was loaded lives on the element itself: route it through the setter.
    if (Object.prototype.hasOwnProperty.call(this, 'getToken')) {
      var value = this.getToken
      delete this.getToken
      this.getToken = value
    }
  }

  proto._emit = function (type, detail) {
    this.dispatchEvent(new CustomEvent(type, { detail: detail, bubbles: true, composed: true }))
  }

  proto._tokenProvider = function () {
    var self = this
    if (typeof this._getToken === 'function') return this._getToken
    var url = this.getAttribute('token-url')
    if (!url) return null
    return function () {
      return fetch(url, {
        method: self.getAttribute('token-method') || 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      }).then(function (response) {
        if (!response.ok) throw new Error('RocketCloud: token endpoint answered ' + response.status)
        return response.json()
      }).then(function (data) {
        if (!data || typeof data.token !== 'string') throw new Error('RocketCloud: the token endpoint must return { "token": "…" }')
        return data.token
      })
    }
  }

  // Frameworks often set properties right after inserting the element: mount on the next microtask.
  proto._scheduleMount = function () {
    var self = this
    if (this._scheduled) return
    this._scheduled = true
    Promise.resolve().then(function () {
      self._scheduled = false
      if (self.isConnected) self._mount()
    })
  }

  proto._mount = function () {
    var self = this
    this._unmount()
    var applicationId = this.getAttribute('application-id')
    var baseUrl = this.getAttribute('base-url') || SCRIPT_ORIGIN
    var getToken = this._tokenProvider()
    if (!applicationId || !baseUrl || !getToken) {
      // Frameworks may set getToken a little later (after rendering): look again once before complaining.
      if (!this._waited) {
        this._waited = true
        setTimeout(function () { if (self.isConnected && !self._instance) self._mount() }, 50)
        return
      }
      this._emit('error', new Error('RocketCloud: application-id and token-url (or the getToken property) are required'))
      return
    }
    this._waited = false
    try {
      this._instance = mount(this._container, {
        baseUrl: baseUrl,
        applicationId: applicationId,
        getToken: getToken,
        mode: this.getAttribute('mode') || undefined,
        accept: this.getAttribute('accept') || undefined,
        folder: this.getAttribute('folder') || undefined,
        title: this.getAttribute('frame-title') || undefined,
        minHeight: Number(this.getAttribute('min-height')) || undefined,
        autoResize: !this.hasAttribute('no-auto-resize'),
        onReady: function () { self._emit('ready') },
        onSelect: function (selection) { self._emit('select', selection) },
        onCancel: function () { self._emit('cancel') },
        onError: function (error) { self._emit('error', error) },
      })
    } catch (error) {
      this._emit('error', error)
    }
  }

  proto._unmount = function () {
    if (this._instance) this._instance.destroy()
    this._instance = null
  }

  proto.connectedCallback = function () {
    this._init()
    this._scheduleMount()
  }

  proto.disconnectedCallback = function () {
    this._unmount()
  }

  proto.attributeChangedCallback = function (name, oldValue, value) {
    if (oldValue === value) return
    this._init()
    if (this.isConnected) this._scheduleMount()
  }

  /** Reloads the current folder (after the host saved a file in it, for instance). */
  proto.refresh = function () {
    if (this._instance) this._instance.refresh()
  }

  Object.defineProperty(proto, 'getToken', {
    get: function () { return this._getToken },
    set: function (fn) {
      this._getToken = fn
      if (this.isConnected) this._scheduleMount()
    },
  })

  Object.defineProperty(proto, 'iframe', {
    get: function () { return this._instance ? this._instance.iframe : null },
  })

  global.customElements.define('rocket-cloud-picker', RocketCloudPicker)
})(window)
