package main

import (
    "http";
    "io";
    "os";
    "fmt";
    "strings";
    "time";
)

var sessions =  map[int]  chan string{}
//var killtimer =  map[string] chan bool{}



func PushServer(c *http.Conn, req *http.Request) {
    
    c.SetHeader("content-type", "text/plain; charset=utf-8");
    c.SetHeader("Server", "jonaz ajax server");
    
    hostname := strings.Split(c.Req.Host,":");

    sess := req.FormValue("sess");

    //example http long fetch request
    //http://localhost:12345/?fetch&sess=112551
    if sess != "" {
        _,test := sessions[sess+hostname[0]];
        if(test ){
            io.WriteString(c, "error, sessions already running fetch");
            sessions[sess+hostname[0]] = nil,false;
            return;
        }
        //sessions[sess].data = make(chan string);
        sessions[sess+hostname[0]] = make(chan string);
        killtimer := make(chan bool);
        go timer(sess+hostname[0],killtimer);
        value := <-sessions[sess+hostname[0]];

        killtimer <- true;

        io.WriteString(c, "query: "+c.Req.URL.RawQuery+"\nPush: \n");
        fmt.Fprintf(c, "val: %s", value);
        os.Stdout.WriteString("Hostname: "+c.Req.Host+" (sess:"+value+") ---");
        os.Stdout.WriteString("value.hostname: "+hostname[0]+"\n");
        sessions[sess+hostname[0]] = nil,false;
        return;
    }

    //safety check. dont want outsiders to push!
    if( hostname[0] != "localhost"){
        io.WriteString(c, "only localhost allowed to push! Nothing to do.....");
        return;
    }

    //example request to push to sessions in "to" variable
    //http://localhost:12345/?cmd=1&to=1,2,3,4,5,6,7,9,10,11,12,13,14&data=12ljalgijasljga
    if len(sessions) > 0 && req.FormValue("cmd") == "1" {
        fmt.Fprintf(c, "Time: %d\n", time.Seconds());
        sendto := strings.Split(req.FormValue("to"),",",0);
        data := req.FormValue("data");
        host := req.FormValue("domain");
        for _,sendtoval := range sendto {
            _,test := sessions[sendtoval+host];
            if test {
                sessions[sendtoval+host] <- data;
                io.WriteString(c, "sending to channel... var: "+data+"\n to session: "+sendtoval+"\nqueue: ");
                fmt.Fprintf(c, "Time: %d\n", time.Seconds());
            }
        }
        return;
    }

    io.WriteString(c, "Nothing to do.....");
    return;

}

func timer(sess string,killme chan bool) {
    limit :=time.Seconds()+5;
    for{
        /*
        _,test := killme;
        if !test {
            return;
        }
        */
        select {
            case <-killme:
                return;
            default:
            if limit < time.Seconds() {
                _,test1 := sessions[sess];
                if test1  {
                    sessions[sess] <- "timeout";
                }
                close(killme);
                return;

            }
            time.Sleep(1000000000);

        }
    }
    return ;
}
func main() {
    http.Handle("/", http.HandlerFunc(PushServer) );
    err := http.ListenAndServe(":12345", nil);
    if err != nil {
        panic("ListenAndServe: ", err.String());
    }
}



