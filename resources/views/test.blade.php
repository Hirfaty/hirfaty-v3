<html>
    <body>
        <form method="POST" action="{{ route("upload") }}" enctype="multipart/form-data">
            <input type="file" name="uploadMe" />
            <input type="submit" />
        </form>
    </body>
</html>