<?php

namespace BinSoul\Net\Mqtt\Packet;

use BinSoul\Net\Mqtt\Exception\MalformedPacketException;
use BinSoul\Net\Mqtt\PacketStream;
use BinSoul\Net\Mqtt\Packet;

/**
 * Represents the CONNECT packet.
 */
class ConnectRequestPacket extends BasePacket
{
    /** @var int */
    private $protocolLevel = 4;
    /** @var string */
    private $protocolName = 'MQTT';
    /** @var int */
    private $flags = 2;
    /** @var string */
    private $clientID = '';
    /** @var int */
    private $keepAlive = 60;
    /** @var string */
    private $willTopic = '';
    /** @var string */
    private $willMessage = '';
    /** @var string */
    private $username = '';
    /** @var string */
    private $password = '';

    protected $packetType = Packet::TYPE_CONNECT;

    public function read(PacketStream $stream)
    {
        parent::read($stream);
        $this->assertPacketFlags(0);
        $this->assertRemainingPacketLength();

        $this->protocolName = $stream->readString();
        $this->protocolLevel = $stream->readByte();
        $this->flags = $stream->readByte();
        $this->keepAlive = $stream->readWord();
        $this->clientID = $stream->readString();

        if ($this->hasWill()) {
            $this->willTopic = $stream->readString();
            $this->willMessage = $stream->readString();
        }

        if ($this->hasUsername()) {
            $this->username = $stream->readString();
        }

        if ($this->hasPassword()) {
            $this->password = $stream->readString();
        }

        if ($this->flags & 1) {
            throw new MalformedPacketException('The reserved bit of the connection flags has to be zero.');
        }

        $this->assertValidWill();
        $this->assertValidString($this->clientID);
        $this->assertValidString($this->willTopic);
        $this->assertValidString($this->username);
    }

    public function write(PacketStream $stream)
    {
        if ($this->clientID == '') {
            $this->clientID = 'BinSoul'.rand(100000, 999999);
        }

        $data = new PacketStream();

        $data->writeString($this->protocolName);
        $data->writeByte($this->protocolLevel);
        $data->writeByte($this->flags);
        $data->writeWord($this->keepAlive);
        $data->writeString($this->clientID);

        if ($this->hasWill()) {
            $data->writeString($this->willTopic);
            $data->writeString($this->willMessage);
        }

        if ($this->hasUsername()) {
            $data->writeString($this->username);
        }

        if ($this->hasPassword()) {
            $data->writeString($this->password);
        }

        $this->remainingPacketLength = $data->length();

        parent::write($stream);
        $stream->write($data->getData());
    }

    /**
     * Returns the protocol level.
     *
     * @return int
     */
    public function getProtocolLevel()
    {
        return $this->protocolLevel;
    }

    /**
     * Sets the protocol level.
     *
     * @param int $value
     */
    public function setProtocolLevel($value)
    {
        if ($value < 3 || $value > 4) {
            throw new \InvalidArgumentException(sprintf('Unknown protocol level %d.', $value));
        }

        $this->protocolLevel = $value;
        if ($this->protocolLevel == 3) {
            $this->protocolName = 'MQIsdp';
        } elseif ($this->protocolLevel == 4) {
            $this->protocolName = 'MQTT';
        }
    }

    /**
     * Returns the client id.
     *
     * @return string
     */
    public function getClientID()
    {
        return $this->clientID;
    }

    /**
     * Sets the client id.
     *
     * @param string $value
     */
    public function setClientID($value)
    {
        if (strlen($value) > 23) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected client id shorter than 24 bytes but got "%s".',
                    $value
                )
            );
        }

        if ($value != '' && !preg_match('/[0-9a-zA-Z]+/', $value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected a client id containing characters 0-9, a-z or A-Z but got "%s".',
                    $value
                )
            );
        }

        $this->clientID = $value;
    }

    /**
     * Returns the keep alive time in seconds.
     *
     * @return int
     */
    public function getKeepAlive()
    {
        return $this->keepAlive;
    }

    /**
     * Sets the keep alive time in seconds.
     *
     * @param int $value
     */
    public function setKeepAlive($value)
    {
        if ($value > 65535) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected a keep alive time lower than 65535 but got %d.',
                    $value
                )
            );
        }

        $this->keepAlive = $value;
    }

    /**
     * Indicates if the clean session flag is set.
     *
     * @return bool
     */
    public function isCleanSession()
    {
        return $this->flags & 2;
    }

    /**
     * Changes the clean session flag.
     *
     * @param bool $value
     */
    public function setCleanSession($value)
    {
        if ($value) {
            $this->flags = $this->flags | 2;
        } else {
            $this->flags = $this->flags & ~2;
        }
    }

    /**
     * Indicates if a will is set.
     *
     * @return bool
     */
    public function hasWill()
    {
        return $this->flags & 4;
    }

    /**
     * Returns the desired quality of service level of the will.
     *
     * @return bool
     */
    public function getWillQosLevel()
    {
        return ($this->flags & 24) >> 3;
    }

    /**
     * Indicates if the will should be retained.
     *
     * @return bool
     */
    public function isWillRetained()
    {
        return $this->flags & 32;
    }

    /**
     * Returns the will topic.
     *
     * @return string
     */
    public function getWillTopic()
    {
        return $this->willTopic;
    }

    /**
     * Returns the will message.
     *
     * @return string
     */
    public function getWillMessage()
    {
        return $this->willMessage;
    }

    /**
     * Sets the will.
     *
     * @param string $topic
     * @param string $message
     * @param int    $qosLevel
     * @param bool   $isRetained
     *
     * @throws MalformedPacketException
     */
    public function setWill($topic, $message, $qosLevel = 0, $isRetained = false)
    {
        $this->assertValidString($topic);
        if (strlen($topic) == 0) {
            throw new \InvalidArgumentException('The topic must not be empty.');
        }

        $this->assertValidStringLength($message);
        if (strlen($message) == 0) {
            throw new \InvalidArgumentException('The message must not be empty.');
        }

        if ($qosLevel < 0 || $qosLevel > 2) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected a quality of service level lower than 3 but got %d.',
                    $qosLevel
                )
            );
        }

        $this->willTopic = $topic;
        $this->willMessage = $message;

        $this->flags = $this->flags | 4;
        $this->flags = $this->flags | ($qosLevel << 3);

        if ($isRetained) {
            $this->flags = $this->flags | 32;
        } else {
            $this->flags = $this->flags & ~32;
        }
    }

    /**
     * Removes the will.
     */
    public function removeWill()
    {
        $this->flags = $this->flags & ~60;
        $this->willTopic = '';
        $this->willMessage = '';
    }

    /**
     * Indicates if a username is set.
     *
     * @return bool
     */
    public function hasUsername()
    {
        return $this->flags & 64;
    }

    /**
     * Returns the username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Sets the username.
     *
     * @param string $value
     */
    public function setUsername($value)
    {
        $this->assertValidString($value);

        $this->username = $value;
        if ($this->username != '') {
            $this->flags = $this->flags | 64;
        } else {
            $this->flags = $this->flags & ~64;
        }
    }

    /**
     * Indicates if a password is set.
     *
     * @return bool
     */
    public function hasPassword()
    {
        return $this->flags & 128;
    }

    /**
     * Returns the password.
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets the password.
     *
     * @param string $value
     */
    public function setPassword($value)
    {
        $this->assertValidStringLength($value);

        $this->password = $value;
        if ($this->password != '') {
            $this->flags = $this->flags | 128;
        } else {
            $this->flags = $this->flags & ~128;
        }
    }

    /**
     * Asserts that all will flags and quality of service are correct.
     *
     * @throws MalformedPacketException
     */
    private function assertValidWill()
    {
        if ($this->hasWill()) {
            if ($this->getWillQosLevel() == 3) {
                throw new MalformedPacketException(
                    'The quality of service level of the will has to be lower than 3.'
                );
            }
        } else {
            if ($this->getWillQosLevel() > 0) {
                throw new MalformedPacketException(
                    sprintf(
                        'Expected a will quality of service level of zero but got %d.',
                        $this->getWillQosLevel()
                    )
                );
            }

            if ($this->isWillRetained()) {
                throw new MalformedPacketException('There is not will but the will retain flag is set.');
            }
        }
    }
}
