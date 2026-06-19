document.addEventListener('alpine:init', () => {
    window.Alpine.data('wysiwyg', (model, placeholder = '') => ({
        tick: 0,

        init() {
            const el = this.$refs.editor

            if (el.dataset.ready) return
            el.dataset.ready = '1'

            try {
                document.execCommand('styleWithCSS', false, false)
            } catch (e) {
                /* sebagian browser melempar; abaikan */
            }

            el.innerHTML = this.$wire.get(model) || ''
            this.refreshEmpty()

            const push = () => {
                this.$wire.set(model, el.innerHTML, false)
                this.refreshEmpty()
            }
            const refresh = () => this.tick++

            el.addEventListener('input', () => {
                push()
                refresh()
            })
            el.addEventListener('keyup', refresh)
            el.addEventListener('mouseup', refresh)
            document.addEventListener('selectionchange', () => {
                if (document.activeElement === el) this.tick++
            })

            this.$wire.$watch(model, (value) => {
                const incoming = value || ''
                if (incoming !== el.innerHTML && document.activeElement !== el) {
                    el.innerHTML = incoming
                    this.refreshEmpty()
                }
            })
        },

        refreshEmpty() {
            const el = this.$refs.editor
            const empty = el.textContent.trim() === '' && !el.querySelector('img')
            el.classList.toggle('is-empty', empty)
        },

        cmd(command, value = null) {
            this.$refs.editor.focus()
            document.execCommand(command, false, value)
            this.$wire.set(model, this.$refs.editor.innerHTML, false)
            this.refreshEmpty()
            this.tick++
        },

        block(tag) {
            const current = (document.queryCommandValue('formatBlock') || '').toLowerCase()
            this.cmd('formatBlock', current === tag ? '<p>' : '<' + tag + '>')
        },

        state(command) {
            this.tick
            try {
                return document.queryCommandState(command)
            } catch (e) {
                return false
            }
        },

        isBlock(tag) {
            this.tick
            try {
                return (document.queryCommandValue('formatBlock') || '').toLowerCase() === tag
            } catch (e) {
                return false
            }
        },

        setLink() {
            const url = window.prompt('Masukkan URL tautan:', 'https://')
            if (url) this.cmd('createLink', url)
        },

        setImage() {
            const url = window.prompt('Masukkan URL gambar:', 'https://')
            if (url) this.cmd('insertImage', url)
        },
    }))
})

/**
 * Komponen Alpine "productLightbox" — galeri layar penuh untuk halaman detail
 * produk. Fitur: klik gambar untuk membesar, tombol prev/next, indikator slide,
 * auto-loop tiap 5 detik, swipe di layar sentuh, dan navigasi keyboard.
 *
 * Pemakaian (lihat pages/storefront/product.blade.php):
 *   x-data="productLightbox(@js($galleryUrls))"
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('productLightbox', (images = []) => ({
        images,
        isOpen: false,
        index: 0,
        timer: null,
        touchX: null,

        // Buka lightbox pada gambar yang URL-nya cocok dengan src gambar utama.
        // Disamakan lewat pathname agar tahan beda host/relatif-absolut.
        openBySrc(src) {
            const norm = (s) => {
                try {
                    return new URL(s, window.location.href).pathname
                } catch (e) {
                    return s
                }
            }
            const target = norm(src)
            const i = this.images.findIndex((u) => norm(u) === target)
            this.open(i >= 0 ? i : 0)
        },

        open(i = 0) {
            if (!this.images.length) return
            this.index = Math.max(0, Math.min(i, this.images.length - 1))
            this.isOpen = true
            document.body.style.overflow = 'hidden'
            this.start()
        },

        close() {
            this.isOpen = false
            this.stop()
            document.body.style.overflow = ''
        },

        next() {
            this.index = (this.index + 1) % this.images.length
            this.restart()
        },

        prev() {
            this.index = (this.index - 1 + this.images.length) % this.images.length
            this.restart()
        },

        go(i) {
            this.index = i
            this.restart()
        },

        // Auto-advance tiap 5 detik (hanya bila ada >1 gambar).
        start() {
            this.stop()
            if (this.images.length > 1) {
                this.timer = setInterval(() => {
                    this.index = (this.index + 1) % this.images.length
                }, 5000)
            }
        },

        stop() {
            if (this.timer) {
                clearInterval(this.timer)
                this.timer = null
            }
        },

        // Reset hitungan 5 detik setiap navigasi manual agar tidak langsung lompat.
        restart() {
            if (this.isOpen) this.start()
        },

        onTouchStart(e) {
            this.touchX = e.changedTouches[0].clientX
        },

        onTouchEnd(e) {
            if (this.touchX === null) return
            const dx = e.changedTouches[0].clientX - this.touchX
            if (Math.abs(dx) > 50) {
                dx < 0 ? this.next() : this.prev()
            }
            this.touchX = null
        },
    }))
})
