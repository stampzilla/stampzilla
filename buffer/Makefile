all:main
main:buffer.go
	8g -o buffer.8 buffer.go 
	8l -o buffer buffer.8
	rm buffer.8
	cp buffer ../source/usr/lib/stampzilla/components/sape
	./buffer
