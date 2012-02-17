package main

import (
    "http";
    "io";
    "log";
    "fmt";
    "os"
    "net"
    "time"
)

import _ "http/pprof"

type Message struct {
    from string
}

var buf [1024000]byte

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
        //fmt.Print("calling handlejson from forloop...\n")
        handleJSON(conn)
    }
}

func main(){
    go httpRunner();
    udpRunner();
}

func handleJSON(conn *net.UDPConn) {

    // Read in the message to the buffer 
    //fmt.Print("start ReadFromUDP...\n")
    stop, _, err := conn.ReadFromUDP(buf[0:])
    if err != nil {
        fmt.Print("err in ReadFromUDP...\n")
        return
    }
    //fmt.Print("ReadFromUDP done...\n")

    // Send the message to all active clients
    //fmt.Print("before for loop sessions stop:"+string((int)(stop))+"..\n")
    for i,xi := range sessions {
        _,test := sessions[i];
        if test {
            //fmt.Print("Write chan ",i,"... ");

            // Special to fix no-blocking, could hapend if one channel buffer is full
            //fmt.Print("select case xi<- string...\n")
            select {
                case xi <- string(buf[0:stop]):
                default:
                    continue
            }

            //fmt.Print("done\n");
        }
    }
    //fmt.Print("for loop done..\n");
}

func PushServer(w http.ResponseWriter, req *http.Request) {

    var channel chan string
    var id string;


    // Read the cookie,if there are any
    cookie,err := req.Cookie("stampzilla")
    if err != nil {
        //fmt.Print("No cookie\n");
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
    w.Header().Add("Expires","Sat, 26 Jul 1997 05:00:00 GMT");
    w.Header().Add("Cache-Control","no-cache, must-revalidate");
    w.Header().Add("Pragma","no-cache");

    //fmt.Print("New connection ",id,", wait for data...\n");

    fmt.Print("Querystring:"+ req.URL.RawQuery+"\n");
    for k, val := range req.URL.Query() {
        fmt.Print("Query():"+ k +" - "+val[0]+"\n");
    }
    // Start wait for data
    if ( req.URL.RawQuery == "start&1" ) {
        io.WriteString(w, "window.sape.onInit();")
    }else{
        writeToHttpFromChan(w,channel);
    }
}

func writeToHttpFromChan( w http.ResponseWriter, channel chan string ) {

    // Create a timeout timer, to kill old sessions
    timeout := make (chan bool)
    go checkTimeout(timeout)

    // Wait for a message or timeout
    select {
        case msg := <-channel:
            //fmt.Print("Starting WriteString...\n")
            io.WriteString(w, "window.sape.recive("+msg+");")
            //fmt.Print("window.sape.recive("+msg+");\n")
            return
        case <-timeout:
            return
    }
}

func checkTimeout (timeout chan bool) {
    time.Sleep(5e9) // 60 sec, (usec)

    // Non blocking command, if the channel is dead
    select {
        case timeout <- true:
		default:
			return
    }
}
