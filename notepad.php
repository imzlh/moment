<?php return function(\MomentCore\HttpHandle $h){

    $h -> header("Cache-Control", "no-store");
    $note = @$h -> client -> param['note'];
    $db = \MomentCore\dbopen('notepad');

    if ($h -> client -> method == 'POST') {
        yield $h -> parseBody();
        $text = @$h -> client -> post['text'];
        if(!$text)
            $text = $h -> client -> body;
        if(!$text)
            return $h -> finish(400,'No content provided');
        $db -> $note = $text;
        return;
    }

    if (!$note || strlen($note) < 10)
        return $h -> finish(302,'',[
            "Location" => '?note=' . md5($h -> addr . rand(10,100))
        ]);

    $ua = strtolower(@$h -> client -> header['user-agent']);
    if (
        isset($h -> client -> param['raw']) || 
        str_starts_with($ua,'curl') ||
        str_starts_with($ua,'wget')
    ) {
        if ($db -> $note)
            $h -> finish(200,$db -> $note,[
                'Content-type' => 'text/plain'
            ]);
        else 
            $h -> finish(404,'Not found');
        return;
    }
?>
<!DOCTYPE html>
<html>

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php print $_GET['note']; ?></title>
        <style>
            body {
                margin: 0;
                background: #ebeef1;
            }
            .container {
                position: absolute;
                top: 20px;
                right: 20px;
                bottom: 20px;
                left: 20px;
            }
            #content {
                font-size: 100%;
                margin: 0;
                padding: 20px;
                overflow-y: auto;
                resize: none;
                width: 100%;
                height: 100%;
                min-height: 100%;
                box-sizing: border-box;
                border: 1px #ddd solid;
                outline: none;
            }
            #printable {
                display: none;
            }

            @media (prefers-color-scheme: dark) {
                body {
                    background: #383934;
                }
                #content {
                    background: #282923;
                    color: #f8f8f2;
                    border: 0;
                }
            }

            @media print {
                .container {
                    display: none;
                }
                #printable {
                    display: block;
                    white-space: pre-wrap;
                    word-break: break-word;
                }
            }
        </style>
    </head>

    <body>
        <div class="container">
            <textarea id="content" placeholder="告诉我你在想什么怎么样？"><?php
                echo @$db -> $note;
            ?></textarea>
        </div>
        <pre id="printable"></pre>
        <script>
            function uploadContent() {
                if(changed) changed = false;
                else return;
                var temp = textarea.value;
                var request = new XMLHttpRequest();
                request.open('POST', window.location.href, true);
                request.setRequestHeader('Content-Type', 'text/plain; charset=UTF-8');
                request.onload = function() {
                    if (request.readyState == 4)
                        content = temp;
                }
                request.send(temp);

                printable.removeChild(printable.firstChild);
                printable.appendChild(document.createTextNode(temp));
            }

            var textarea = document.getElementById('content');
            var printable = document.getElementById('printable');
            var content = textarea.value;
            var changed = false;

            printable.appendChild(document.createTextNode(content));

            textarea.focus();
            textarea.oninput = function(){
                changed = true;
            }
            setInterval(uploadContent,10000);
        </script>
    </body>

</html>
<?php } ?>