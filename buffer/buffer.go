package main

import (
    "http";
    "io";
	"log";
	"fmt";
	"strings"
	"os"
	"net"
	"time"
)

type Message struct {
    from string
}

// List of all active sessions
var sessions = map[string] chan string{}

// HTTP listner
func httpRunner() {
	http.HandleFunc("/", PushServer)
	err := http.ListenAndServe(":12345", nil)
	if err != nil {
		log.Fatal("ListenAndServe: ", err.String())
	}
}

// UDP listner
func udpRunner() {
	udpAddr, err := net.ResolveUDPAddr("up4", ":8282")
	if err != nil {
		fmt.Fprintf(os.Stderr, "Fatal error ", err.String())
		os.Exit(1)
	}

	conn, err := net.ListenUDP("udp", udpAddr)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Fatal error ", err.String())
		os.Exit(1)
	}

	for {
		handleJSON(conn)
	}
}

func main(){
	go httpRunner();
	udpRunner();
}

func handleJSON(conn *net.UDPConn) {

	var buf [512]byte

	// Read in the message to the buffer 
	_, _, err := conn.ReadFromUDP(buf[0:])
	if err != nil {
		return
	}

	// Send the message to all active clients
	for i,xi := range sessions {
        _,test := sessions[i];
		if test {
			//fmt.Print("Write chan ",i,"... ");

			// Special to fix no-blocking, could hapend if one channel buffer is full
			select{
				case xi <- strings.TrimRight(string(buf[:]),string(0) ):
				default:
			}

			//fmt.Print("done\n");
		}
	}
	
	//fmt.Print("JSON from ",addr.IP,": \n");
}

func PushServer(w http.ResponseWriter, req *http.Request) {

	var channel chan string
	var id string;


	// Read the cookie,if there are any
	cookie,err := req.Cookie("stampzilla")
	if err != nil {
		fmt.Print("No cookie\n");
	} else {
		id = cookie.Value
		
		//fmt.Print("Cookie: ",cookie.Value);
	}

	// Test if the channel is available
    _,test := sessions[id];

	// If channel was not found
	if ( id == "" || !test) {
		// Create a new bufferd channel
		channel = make(chan string,50)

		// Create a new session ID
		t := time.LocalTime()
		id = t.Format("20060102150405")

		// Save the channel
		sessions[id] = channel

	} else {
		// Select the old channel
		channel = sessions[id]
	}

	// Set the content type
	w.Header().Add("Content-Type","text/html");

	// And add the cookie
	w.Header().Add("Set-Cookie","stampzilla="+id);

	//fmt.Print("New connection ",id,", wait for data...\n");

	// Start wait for data
	writeToHttpFromChan(w,channel);
}

func writeToHttpFromChan( w http.ResponseWriter, channel chan string ) {

	// Create a timeout timer, to kill old sessions
	timeout := make (chan bool)
	go checkTimeout(timeout)

	// Wait for a message or timeout
	select {
		case msg := <-channel:
			io.WriteString(w, msg)
		case <-timeout:
			return
	}
}

func checkTimeout (timeout chan bool) {
	time.Sleep(60e9) // 60 sec, (usec)
	timeout <- true // Send timeout
}
