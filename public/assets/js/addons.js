define([], function () {
    require([], function () {
    //绑定data-toggle=addresspicker属性点击事件

    $(document).on('click', "[data-toggle='addresspicker']", function () {
        var that = this;
        var callback = $(that).data('callback');
        var input_id = $(that).data("input-id") ? $(that).data("input-id") : "";
        var lat_id = $(that).data("lat-id") ? $(that).data("lat-id") : "";
        var lng_id = $(that).data("lng-id") ? $(that).data("lng-id") : "";
        var lat = lat_id ? $("#" + lat_id).val() : '';
        var lng = lng_id ? $("#" + lng_id).val() : '';
        var url = "/addons/address/index/select";
        url += (lat && lng) ? '?lat=' + lat + '&lng=' + lng : '';
        Fast.api.open(url, '位置选择', {
            callback: function (res) {
                input_id && $("#" + input_id).val(res.address).trigger("change");
                lat_id && $("#" + lat_id).val(res.lat).trigger("change");
                lng_id && $("#" + lng_id).val(res.lng).trigger("change");
                try {
                    //执行回调函数
                    if (typeof callback === 'function') {
                        callback.call(that, res);
                    }
                } catch (e) {

                }
            }
        });
    });
});

require.config({
    paths: {
        'clicaptcha': '../addons/clicaptcha/js/clicaptcha'
    },
    shim: {
        'clicaptcha': {
            deps: [
                'jquery',
                'css!../addons/clicaptcha/css/clicaptcha.css'
            ],
            exports: '$.fn.clicaptcha'
        }
    }
});

require(['clicaptcha'], function () {
    window.clicaptcha = function (captcha) {
        if (captcha.size() > 0) {
            var form = captcha.closest("form");
            var parentDom = captcha.parent();
            // 非文本验证码
            if ($("a[data-event][data-url]", parentDom).size() > 0) {
                return;
            }
            if (captcha.parentsUntil(form, "div.form-group").length > 0) {
                captcha.parentsUntil(form, "div.form-group").addClass("hidden");
            } else if (parentDom.is("div.input-group")) {
                parentDom.addClass("hidden");
            }
            captcha.attr("data-rule", "required");
            // 验证失败时进行操作
            captcha.on('invalid.field', function (e, result, me) {
                //必须删除errors对象中的数据，否则会出现Layer的Tip
                delete me.errors['captcha'];
                if (result.key === 'captcha') {
                    captcha.clicaptcha({
                        src: '/addons/clicaptcha/index/start',
                        success_tip: '验证成功！',
                        error_tip: '未点中正确区域，请重试！',
                        callback: function (captchainfo) {
                            form.trigger("submit");
                            return false;
                        }
                    });
                }
            });
            // 监听表单错误事件
            form.on("error.form", function (e, data) {
                captcha.val('');
            });
        }
    };
    clicaptcha($("input[name=captcha]"));
});

require.config({
    paths: {
        'editable': '../libs/bootstrap-table/dist/extensions/editable/bootstrap-table-editable.min',
        'x-editable': '../addons/editable/js/bootstrap-editable.min',
    },
    shim: {
        'editable': {
            deps: ['x-editable', 'bootstrap-table']
        },
        "x-editable": {
            deps: ["css!../addons/editable/css/bootstrap-editable.css"],
        }
    }
});
if ($("table.table").size() > 0) {
    require(['editable', 'table'], function (Editable, Table) {
        $.fn.bootstrapTable.defaults.onEditableSave = function (field, row, oldValue, $el) {
            var data = {};
            data["row[" + field + "]"] = row[field];
            Fast.api.ajax({
                url: this.extend.edit_url + "/ids/" + row[this.pk],
                data: data
            });
        };
    });
}
if ($('.kdniao').length > 0) {

    $('.kdniao').each(function () {
        var code = $(this).data('code');

        $(this).addClass('btn btn-xs bg-success').append('<i class="fa fa-truck"></i>' + code);
    });

    $('.kdniao').click(function () {
        var company = $(this).data('company');
        var code = $(this).data('code');

        if (company && code) {
            Layer.open({
                type: 2,
                area: ['700px', '450px'],
                fixed: false, //不固定
                maxmin: true,
                content: '/addons/kdniao/index/query?company=' + company + '&code=' + code
            });
        }
    });
}
//修改上传的接口调用
require(['upload'], function (Upload) {

    //初始化中完成判断
    Upload.events.onInit = function () {
        //如果上传接口不是七牛云，则不处理
        if (this.options.url !== Config.upload.uploadurl) {
            return;
        }
        var _success = this.options.success;

        $.extend(this.options, {
            chunkSuccess: function (chunk, file, response) {
                this.contexts = this.contexts ? this.contexts : [];
                this.contexts.push(typeof response.ctx !== 'undefined' ? response.ctx : response.data.ctx);
            },
            chunksUploaded: function (file, done) {
                var that = this;
                var params = $(that.element).data("params") || {};
                var category = typeof params.category !== 'undefined' ? params.category : ($(that.element).data("category") || '');
                Fast.api.ajax({
                    url: "/addons/qiniu/index/upload",
                    data: {
                        action: 'merge',
                        filesize: file.size,
                        filename: file.name,
                        chunkid: file.upload.uuid,
                        chunkcount: file.upload.totalChunkCount,
                        width: file.width || 0,
                        height: file.height || 0,
                        type: file.type,
                        category: category,
                        qiniutoken: Config.upload.multipart.qiniutoken,
                        contexts: this.contexts
                    },
                }, function (data, ret) {
                    done(JSON.stringify(ret));
                    return false;
                }, function (data, ret) {
                    file.accepted = false;
                    that._errorProcessing([file], ret.msg);
                    return false;
                });

            },
        });

        //先移除已有的事件
        this.off("success", _success).on("success", function (file, response) {
            var that = this;
            var ret = {code: 0, msg: response};
            try {
                ret = typeof response === 'string' ? JSON.parse(response) : response;
                if (file.xhr.status === 200) {
                    if (typeof ret.key !== 'undefined') {
                        ret = {code: 1, msg: "", data: {url: '/' + ret.key, hash: ret.hash}};
                    }
                    console.log(ret);
                    var params = $(that.element).data("params") || {};
                    var category = typeof params.category !== 'undefined' ? params.category : ($(that.element).data("category") || '');
                    Fast.api.ajax({
                        url: "/addons/qiniu/index/notify",
                        data: {name: file.name, url: ret.data.url, hash: ret.data.hash, size: file.size, width: file.width || 0, height: file.height || 0, type: file.type, category: category, qiniutoken: Config.upload.multipart.qiniutoken}
                    }, function () {
                        return false;
                    }, function () {
                        return false;
                    });
                }
            } catch (e) {
                console.error(e);
            }
            _success.call(this, file, ret);
        });

        //如果是直传模式
        if (Config.upload.uploadmode === 'client') {
            var _url = this.options.url;

            //分片上传时URL链接不同
            this.options.url = function (files) {
                this.options.headers = {"Authorization": "UpToken " + Config.upload.multipart.qiniutoken};
                if (files[0].upload.chunked) {
                    var chunk = null;
                    files[0].upload.chunks.forEach(function (item) {
                        if (item.status === 'uploading') {
                            chunk = item;
                        }
                    });
                    if (!chunk) {
                        return Config.upload.uploadurl + '/mkfile/' + files[0].size;
                    } else {
                        return Config.upload.uploadurl + '/mkblk/' + chunk.dataBlock.data.size;
                    }
                }
                return _url;
            };

            this.options.params = function (files, xhr, chunk) {
                var params = Config.upload.multipart;
                if (chunk) {
                    return $.extend({}, params, {
                        filesize: chunk.file.size,
                        filename: chunk.file.name,
                        chunkid: chunk.file.upload.uuid,
                        chunkindex: chunk.index,
                        chunkcount: chunk.file.upload.totalChunkCount,
                        chunkfilesize: chunk.dataBlock.data.size,
                        width: chunk.file.width || 0,
                        height: chunk.file.height || 0,
                        type: chunk.file.type,
                    });
                } else {
                    var retParams = $.extend({}, params);
                    //七牛云直传使用的是token参数
                    retParams.token = retParams.qiniutoken;
                    delete retParams.qiniutoken;
                    return retParams;
                }
            };

            //分片上传时需要变更提交的内容
            this.on("sending", function (file, xhr, formData) {
                if (file.upload.chunked) {
                    var _send = xhr.send;
                    xhr.send = function () {
                        var chunk = null;
                        file.upload.chunks.forEach(function (item) {
                            if (item.status == 'uploading') {
                                chunk = item;
                            }
                        });
                        if (chunk) {
                            _send.call(xhr, chunk.dataBlock.data);
                        }
                    };
                }
            });
        }
    };

});

require.config({
    paths: {
        'simditor': '../addons/simditor/js/simditor.min',
    },
    shim: {
        'simditor': [
            'css!../addons/simditor/css/simditor.min.css'
        ]
    }
});
require(['form'], function (Form) {
    var _bindevent = Form.events.bindevent;
    Form.events.bindevent = function (form) {
        _bindevent.apply(this, [form]);
        if ($(".editor", form).size() > 0) {
            //修改上传的接口调用
            require(['upload', 'simditor'], function (Upload, Simditor) {
                var editor, mobileToolbar, toolbar;
                Simditor.locale = 'zh-CN';
                Simditor.list = {};
                toolbar = ['title', 'bold', 'italic', 'underline', 'strikethrough', 'fontScale', 'color', '|', 'ol', 'ul', 'blockquote', 'code', 'table', '|', 'link', 'image', 'hr', '|', 'indent', 'outdent', 'alignment'];
                mobileToolbar = ["bold", "underline", "strikethrough", "color", "ul", "ol"];
                $(".editor", form).each(function () {
                    var id = $(this).attr("id");
                    editor = new Simditor({
                        textarea: this,
                        toolbarFloat: false,
                        toolbar: toolbar,
                        pasteImage: true,
                        defaultImage: Config.__CDN__ + '/assets/addons/simditor/images/image.png',
                        upload: {url: '/'}
                    });
                    editor.uploader.on('beforeupload', function (e, file) {
                        Upload.api.send(file.obj, function (data) {
                            var url = Fast.api.cdnurl(data.url);
                            editor.uploader.trigger("uploadsuccess", [file, {success: true, file_path: url}]);
                        });
                        return false;
                    });
                    editor.on("blur", function () {
                        this.textarea.trigger("blur");
                    });
                    Simditor.list[id] = editor;
                });
            });
        }
    }
});
if (Config.modulename === 'index' && Config.controllername === 'user' && ['login', 'register'].indexOf(Config.actionname) > -1 && $("#register-form,#login-form").size() > 0) {
    $('<style>.social-login{display:flex}.social-login a{flex:1;margin:0 2px;}.social-login a:first-child{margin-left:0;}.social-login a:last-child{margin-right:0;}</style>').appendTo("head");
    $("#register-form,#login-form").append('<div class="form-group social-login"></div>');
    if (Config.third.status.indexOf("wechat") > -1) {
        $('<a class="btn btn-success" href="' + Fast.api.fixurl('/third/connect/wechat') + '"><i class="fa fa-wechat"></i> 微信登录</a>').appendTo(".social-login");
    }
    if (Config.third.status.indexOf("qq") > -1) {
        $('<a class="btn btn-info" href="' + Fast.api.fixurl('/third/connect/qq') + '"><i class="fa fa-qq"></i> QQ登录</a>').appendTo(".social-login");
    }
    if (Config.third.status.indexOf("weibo") > -1) {
        $('<a class="btn btn-danger" href="' + Fast.api.fixurl('/third/connect/weibo') + '"><i class="fa fa-weibo"></i> 微博登录</a>').appendTo(".social-login");
    }
}

});