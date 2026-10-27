package ru.sail.salesoutlets.api;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

@SpringBootApplication(scanBasePackages = {"ru.sail.salesoutlets", "ru.sail.messenger"})
public class SalesOutletsApiApplication {

    public static void main(String[] args) {
        SpringApplication.run(SalesOutletsApiApplication.class, args);
    }
}
