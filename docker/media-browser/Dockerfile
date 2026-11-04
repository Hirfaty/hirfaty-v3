# Read-only browser for production's photos and for photo archives uploaded
# through its page — see server.py. Built on the SeaweedFS image so the same
# `weed` binary that wrote a backup can be started on a copy of it.
FROM chrislusf/seaweedfs

RUN apk add --no-cache python3

COPY docker/media-browser/server.py /app/server.py

EXPOSE 8090

ENTRYPOINT ["python3", "/app/server.py"]
