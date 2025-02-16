# LazyWeb - Vulnerable Web Application

**LazyWeb** is a demonstration web application designed to showcase common server-side application vulnerabilities. Each vulnerability is categorized with its respective difficulty rating to provide a comprehensive learning experience for developers and security enthusiasts.

## Key Features
- Demonstrates real-world vulnerabilities in web applications.
- Ranges from basic to advanced security flaws, allowing users to test their skills at various levels.
- Aimed at helping security professionals understand the nature of vulnerabilities and how to mitigate them.

## Vulnerabilities Included
The application includes the following vulnerabilities:
- OS Command Injection
- SQL Injection
- XML External Entity Injection (XXE)
- Insecure Direct Object Reference (IDOR)
- Local File Inclusion (LFI)
- Insecure File Upload
- Arbitrary Session Assignment
- Broken Authorization
- Broken Authentication
- .git Folder Disclosure (if cloned directly to web root)

## Deployment Instructions
To get started with the project, simply run the following command:

```bash
docker-compose up
```

This will set up the environment and start the application using Docker.

### Important Notes
- Ensure that the `templates_c` and `user/avatar` directories have the correct ownership. After deploying, run:
  
  ```bash
  sudo chown www-data:www-data templates_c user/avatar
  ```

## Write-ups and Resources
- [LFI to RCE via PHAR](https://www.jasveermaan.com)
- [Writeup on Pojiiix](https://blog.pojiiix.tech)

## License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
