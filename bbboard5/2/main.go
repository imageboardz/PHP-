package main

import (
	"encoding/json"
	"fmt"
	"io/ioutil"
)

type Post struct {
	Name string `json:"name"`
	Text string `json:"text"`
	Date string `json:"date"`
}

func main() {
	// Generate 1000 sample posts
	posts := make([]Post, 1000)
	for i := 0; i < 1000; i++ {
		post := Post{
			Name: fmt.Sprintf("User %d", i+1),
			Text: fmt.Sprintf("%d. Here is a fork of 4shout. The original author did a nice job so I made this fork to support them.", i+1),
			Date: "2023/02/23 10:00:00",
		}
		posts[i] = post
	}

	// Write the posts to the database file
	fileName := "database.json"
	data, err := json.Marshal(posts)
	if err != nil {
		fmt.Printf("Error marshaling JSON: %s\n", err)
		return
	}
	err = ioutil.WriteFile(fileName, data, 0644)
	if err != nil {
		fmt.Printf("Error writing file: %s\n", err)
		return
	}

	fmt.Printf("Wrote %d posts to %s\n", len(posts), fileName)
}
