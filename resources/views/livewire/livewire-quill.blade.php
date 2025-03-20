<div wire:ignore>  {{-- Since I am changing things, do allow wrapper_class here, it might make styling easier. --}}
    <div
        id="{{ $quillId }}"
        class="{{ $classes }} {{ config('livewire-quill.editor_classes') }} livewire-quill"
        name="{{ $quillId }}"
        wire:key="quill-{{ $quillId }}"
    ></div>

    @assets
    <link
        href="/vendor/livewire-quill/quill.snow.min.css"
        rel="stylesheet"
    >
    <script src="/vendor/livewire-quill/quill.js"></script>
    @endassets

    @script    
    <script>
        var quillContainer = null;

        function initQuill(id, parent_property, placeholder, toolbar) {
            var content = null;
            var init = true;

            function selectLocalImage() {
                const input = document.createElement('input');
                input.setAttribute('type', 'file');
                input.click();

                // Listen upload local image and save to server
                input.onchange = () => {
                    const file = input.files[0];

                    // file type is only image.
                    if (/^image\//.test(file.type)) {
                        imageHandler(file);
                    } else {
                        alert('You can only upload images.');
                    }
                };
            }

            function imageHandler(image) {
                var uploadedImagesBefore = @this.quillUploadedImages;

                @this.uploadMultiple('quillImages', [image], (uploadedFilename) => {
                    // now get images after upload
                    var uploadedImagesAfterUpload = @this.quillUploadedImages;

                    var imageName = uploadedFilename;
                    var imageUrl = null;

                    for (var key in uploadedImagesAfterUpload) {
                        if (uploadedImagesAfterUpload.hasOwnProperty(key)) {
                            imageUrl = uploadedImagesAfterUpload[key];
                        }
                    }

                    if (imageUrl) {
                        imageUrl = '/storage/' + imageUrl;
                    }

                    insertToEditor(imageUrl, content);
                });
            }

            function insertToEditor(url, editor) {
                const range = editor.getSelection();
                editor.insertEmbed(range.index, 'image', url);
            }

            content = new Quill(`#${id}`, {
                modules: {
                    toolbar: toolbar,
                },
                placeholder: placeholder,
                theme: "snow",
            });

            content.getModule('toolbar').addHandler('image', () => {
                selectLocalImage();
            });

            content.on("text-change", (delta, oldDelta, source) => {
                if (source === "user") {
                    let currrentContents = content.getContents();
                    let diff = currrentContents.diff(oldDelta);
                    try {
                        // loop through diff.ops to find image
                        diff.ops.forEach((op) => {
                            if (op.hasOwnProperty('insert')) {
                                if (op.insert.hasOwnProperty('image')) {
                                    // get image url
                                    var imageUrl = op.insert.image;

                                    if (imageUrl) {
                                        @this.deleteImage(imageUrl);
                                    }
                                }
                            }
                        });
                    } catch (_error) {

                    }
                }
            });

            content.root.innerHTML = data;

            // on content change
            // we cannot get parent here (I guess that is the reason why this is not allowed to run during init)
            // question is if we can get 'content' variable from here in Livewire.hook below...
            // I also worry about proper cleanup
            // content here is not referenced outside, so there has to be some hidden reference of it risks garbage collection, right?
            // but if we remove the livewire-quill component, will the content be removed?
            content.on("text-change", function(delta, oldDelta, source) {
                if (init) {
                    return;
                }

                // debounce it
                clearTimeout(quillContainer);

                // set a timeout to see if the user is still typing
                quillContainer = setTimeout(function() {
                    // set the content to the model
                    //@this.dispatch('contentChanged', {
                    //    editorId: content.container.id,
                    //    content: content.root.innerHTML
                    //})
                    @this.parent.$wire.set(parent_property, content.root.innerHTML);
                }, 500);
            });

            init = false;
        }

        document.addEventListener('livewire-quill:init', (event) => {
            var event = event.detail[0];

            var quillContainer = document.getElementById(event.quillId);

            if (!quillContainer.dataset.initialized) {
                initQuill(event.quillId, event.parent_property, event.placeholder, event.toolbar);
                quillContainer.dataset.initialized = true;
            }
        });

        window.Livewire.hook('component.init', ({ component, cleanup }) => {
             console.log("JA component.init", component, cleanup);
            //todo: frankly we might want to move the content.on('text-change') here, since we might avoid the if (init) check there, the component is ready here
             if (component.name == 'livewire-quill')
             {
                 console.log("JA: quill found", component.canonical.quillId);

                 component.parent.$wire.watch("text", (value, old) => {
                    console.log("JA watch text from parent", value, old);
                    content.root.innerHTML = value;  // todo: problem here is how to get content and how to destroy when needed, we cannot leave it around, that would cause memory leaks
                });
             }
        });
    </script>
    @endscript
</div>
