PHP ?= php
IMAGE ?= sepa-creator:local
.PHONY: test lint image test-container
test:
	$(PHP) tests/run.php
lint:
	find src tests bin -type f -exec $(PHP) -l {} \;
image:
	docker build -t "$(IMAGE)" .
test-container:
	timeout --foreground 60s docker run --rm --network none --read-only --cap-drop ALL --security-opt no-new-privileges --memory 256m --pids-limit 64 --tmpfs /tmp:rw,noexec,nosuid,size=32m,mode=1777 --entrypoint php "$(IMAGE)" /app/tests/run.php
