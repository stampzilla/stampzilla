all:main
main:buffer.go
	8g buffer.go
	8l buffer.8
	rm buffer.8
	mv 8.out buffer
	cp buffer ../source/usr/lib/stampzilla/components/ajaxPushBuffer
	./buffer
