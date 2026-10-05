# TCP proxy 0.0.0.0:9223 -> 127.0.0.1:9222 so WSL sidecar can reach Windows Chrome CDP.
$ErrorActionPreference = 'Stop'
Add-Type -TypeDefinition @"
using System;
using System.Net;
using System.Net.Sockets;
using System.Threading;

public static class CdpProxy
{
    public static void Run(int listenPort, string targetHost, int targetPort)
    {
        var listener = new TcpListener(IPAddress.Any, listenPort);
        listener.Start();
        Console.WriteLine("cdp-proxy listening on 0.0.0.0:" + listenPort + " -> " + targetHost + ":" + targetPort);
        while (true)
        {
            var client = listener.AcceptTcpClient();
            ThreadPool.QueueUserWorkItem(_ => Handle(client, targetHost, targetPort));
        }
    }

    static void Handle(TcpClient client, string host, int port)
    {
        TcpClient remote = null;
        try
        {
            remote = new TcpClient();
            remote.Connect(host, port);
            var cstream = client.GetStream();
            var rstream = remote.GetStream();
            var t1 = new Thread(() => Copy(cstream, rstream));
            var t2 = new Thread(() => Copy(rstream, cstream));
            t1.IsBackground = true;
            t2.IsBackground = true;
            t1.Start();
            t2.Start();
            t1.Join();
            t2.Join();
        }
        catch (Exception ex)
        {
            Console.WriteLine("proxy session error: " + ex.Message);
        }
        finally
        {
            try { client.Close(); } catch { }
            try { if (remote != null) remote.Close(); } catch { }
        }
    }

    static void Copy(NetworkStream from, NetworkStream to)
    {
        var buf = new byte[8192];
        try
        {
            int n;
            while ((n = from.Read(buf, 0, buf.Length)) > 0)
            {
                to.Write(buf, 0, n);
            }
        }
        catch { }
    }
}
"@

[CdpProxy]::Run(9223, '127.0.0.1', 9222)
