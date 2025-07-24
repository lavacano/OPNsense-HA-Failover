MIT License

Copyright (c) 2024 OPNsense HA Failover Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

## Attribution

This project builds upon the foundational work of:

- **spali**: Original single-script OPNsense HA solution
  - GitHub Gist: https://gist.github.com/spali/2da4f23e488219504b2ada12ac59a7dc

- **lavacano**: Enhanced iterations and production improvements  
  - GitHub Gist: https://gist.github.com/lavacano/a678e65d31df9bec344e572461ed3e10

## Disclaimer

This software is designed for use with OPNsense firewall systems. Users are
responsible for:

- Testing thoroughly in non-production environments before deployment
- Understanding the implications of high-availability configurations
- Ensuring proper backup and recovery procedures are in place
- Monitoring system behavior after installation

Network infrastructure changes can have significant impacts. Always follow
proper change management procedures and have rollback plans ready.

## Third-Party Components

This software interacts with and depends on:

- OPNsense (BSD 2-Clause License)
- FreeBSD system utilities (BSD License)
- PHP runtime (PHP License)

Users must comply with the licenses of all dependencies.
