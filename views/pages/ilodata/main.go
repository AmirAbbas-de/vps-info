package main

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"net/http"
	"sync"
	"time"

	_ "github.com/go-sql-driver/mysql"
	probing "github.com/prometheus-community/pro-bing"
)

var (
	resultsMap = make(map[string]string)
	mutex      sync.RWMutex
)

const (
	DB_USER = "ilo_user"
	DB_PASS = "123456"
	DB_HOST = "192.168.100.78"
	DB_NAME = "whmcs"
)

func main() {
	go startScanner()

	http.HandleFunc("/all-stats", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Access-Control-Allow-Origin", "*")
		w.Header().Set("Content-Type", "application/json")
		mutex.RLock()
		json.NewEncoder(w).Encode(resultsMap)
		mutex.RUnlock()
	})

	fmt.Println("Ping Engine (5-Packet Mode) running on :8080")
	http.ListenAndServe(":8080", nil)
}

func startScanner() {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:3306)/%s", DB_USER, DB_PASS, DB_HOST, DB_NAME)
	for {
		startTime := time.Now()
		db, err := sql.Open("mysql", dsn)
		if err != nil {
			time.Sleep(5 * time.Second)
			continue
		}

		query := `SELECT DISTINCT sd.ip, JSON_UNQUOTE(JSON_EXTRACT(sui.arp, '$.primary_ip')) 
		          FROM server_details sd 
		          INNER JOIN server_mac sm ON sd.id = sm.server_details_id 
		          INNER JOIN server_usable_ips sui ON sm.id = sui.server_mac_id`
		
		rows, err := db.Query(query)
		if err != nil {
			db.Close()
			time.Sleep(5 * time.Second)
			continue
		}

		var wg sync.WaitGroup
		for rows.Next() {
			var ip1, ip2 sql.NullString
			rows.Scan(&ip1, &ip2)
			
			ips := []string{ip1.String, ip2.String}
			for _, ip := range ips {
				if ip == "" { continue }
				wg.Add(1)
				go func(target string) {
					defer wg.Done()
					status := multiPing(target)
					mutex.Lock()
					resultsMap[target] = status
					mutex.Unlock()
				}(ip)
			}
		}
		rows.Close()
		db.Close()
		wg.Wait()

		// Logic to ensure exactly 20-second cycles
		elapsed := time.Since(startTime)
		if elapsed < 20*time.Second {
			time.Sleep(20*time.Second - elapsed)
		}
	}
}

func multiPing(ip string) string {
	pinger, err := probing.NewPinger(ip)
	if err != nil { return "offline" }
	
	pinger.Count = 5            // Send 5 packets
	pinger.Timeout = time.Second * 4 
	pinger.Interval = time.Millisecond * 100 // Fast interval between packets
	pinger.SetPrivileged(false)

	err = pinger.Run()
	stats := pinger.Statistics()
	
	// If PacketsRecv > 0, at least one ping succeeded = Online
	if err == nil && stats.PacketsRecv > 0 {
		return "online"
	}
	return "offline"
}